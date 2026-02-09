<?php
require_once 'db.php';
// ... (Logic for saving POST remains similar, we will handle it via AJAX mostly or final submit) ...
// Processing logic moved to top to handle traditional submits or via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (haAlcanzadoLimite('cotizaciones')) {
        $error = "Has alcanzado el límite de cotizaciones permitido.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Recopilar datos (misma lógica que antes pero adaptada si es necesario)
            // ... (keeping existing logic roughly same for compatibility first) ...
            
            $config_aceptacion = json_encode([
                'requeridos' => $_POST['campos_requeridos'] ?? [],
                'mensaje_exito' => $_POST['mensaje_exito'] ?? '¡Gracias por aceptar la propuesta!'
            ]);
            $config_pasos = json_encode($_POST['pasos_siguientes'] ?? []);
            $hash_publico = bin2hex(random_bytes(16));
            
            // Normalizar IDs
            $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
            $plantilla_id = !empty($_POST['plantilla_id']) ? $_POST['plantilla_id'] : null;
            $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

            // Extract Form Data (Fix for undefined variables)
            $formulario_id = !empty($_POST['formulario_id']) ? $_POST['formulario_id'] : null;
            $raw_modo = $_POST['formulario_modo'] ?? 'ninguno';
            $formulario_config = json_encode(['modo' => $raw_modo]);

            // Insert/Update Logic
            // If ID exists (draft), UPDATE. Else INSERT.
            $action = 'insert'; 
            $cot_id = $_POST['cotizacion_id'] ?? null;
            
            if($cot_id) {
                // Check consistency
                 $stmtCk = $pdo->prepare("SELECT id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
                 $stmtCk->execute([$cot_id, getEmpresaId()]);
                 if($stmtCk->fetch()) $action = 'update';
            }

            if($action === 'insert') {
                // 1. Validar unicidad del número de cotización
                $numero_final = $_POST['numero_cotizacion'];
                $empresa_id = getEmpresaId();

                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones WHERE numero_cotizacion = ? AND empresa_id = ?");
                $stmtCheck->execute([$numero_final, $empresa_id]);

                if ($stmtCheck->fetchColumn() > 0) {
                    // Si existe, generar uno nuevo basado en la secuencia real
                    $stmtConf = $pdo->prepare("SELECT starting_quote_number, quote_prefix, quote_suffix FROM empresas WHERE id = ? FOR UPDATE");
                    $stmtConf->execute([$empresa_id]);
                    $conf = $stmtConf->fetch();

                    $next_num = $conf['starting_quote_number'];
                    
                    // Loop de seguridad por si el consecutivo también está ocupado (evitar colisiones)
                    do {
                        $numero_final = ($conf['quote_prefix'] ?? '') . $next_num . ($conf['quote_suffix'] ?? '');
                        $stmtCheck->execute([$numero_final, $empresa_id]);
                        $exists = $stmtCheck->fetchColumn() > 0;
                        if ($exists) {
                            $next_num++;
                        }
                    } while ($exists);

                    // Actualizar el puntero DEFERIDO (para evitar doble update)
                    $force_update_number = $next_num + 1;
                }

                $stmt = $pdo->prepare("INSERT INTO cotizaciones (empresa_id, usuario_id, cliente_id, plantilla_id, numero_cotizacion, titulo_cotizacion, fecha, fecha_vencimiento, notas, notas_internas, subtotal, impuestos, total, estado, hash_publico, config_aceptacion, config_pasos, tareas_cliente, contenido_html, mostrar_cantidad_como, requiere_recoleccion, conversion_automatica, notificar_vistas_wa, formulario_id, formulario_config) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Borrador', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    getEmpresaId(),
                    $_SESSION['user_id'],
                    $cliente_id,
                    $plantilla_id,
                    $numero_final, // Usar el número verificado
                    $_POST['titulo_cotizacion'] ?? '',
                    $_POST['fecha'],
                    $fecha_vencimiento,
                    $_POST['notas'],
                    $_POST['notas_internas'] ?? '',
                    $_POST['total_subtotal'],
                    $_POST['total_impuestos'],
                    $_POST['total_general'],
                    $hash_publico,
                    $config_aceptacion,
                    $config_pasos,
                    json_encode($_POST['tareas_cliente'] ?? []), // Guardar tareas_cliente
                    $_POST['contenido_html_custom'] ?? '',       // Guardar contenido_html
                    $_POST['mostrar_cantidad_como'] ?? 'unidad',
                    isset($_POST['requiere_recoleccion']) ? 1 : 0,
                    isset($_POST['conversion_automatica']) ? 1 : 0, // Guardar conversion_automatica
                    isset($_POST['notificar_vistas_wa']) ? 1 : 0,
                    $formulario_id,
                    $formulario_config
                ]);
                $cot_id = $pdo->lastInsertId();
            } else {
                  $stmt = $pdo->prepare("UPDATE cotizaciones SET cliente_id=?, plantilla_id=?, numero_cotizacion=?, titulo_cotizacion=?, fecha=?, fecha_vencimiento=?, notas=?, notas_internas=?, subtotal=?, impuestos=?, total=?, config_aceptacion=?, config_pasos=?, tareas_cliente=?, contenido_html=?, mostrar_cantidad_como=?, requiere_recoleccion=?, conversion_automatica=?, notificar_vistas_wa=?, formulario_id=?, formulario_config=?, usuario_id=? WHERE id=?");
                  $stmt->execute([
                     $cliente_id,
                     $plantilla_id,
                     $_POST['numero_cotizacion'],
                     $_POST['titulo_cotizacion'] ?? '',
                     $_POST['fecha'],
                     $fecha_vencimiento,
                     $_POST['notas'],
                     $_POST['notas_internas'] ?? '',
                     $_POST['total_subtotal'],
                     $_POST['total_impuestos'],
                     $_POST['total_general'],
                     $config_aceptacion,
                     $config_pasos,
                     json_encode($_POST['tareas_cliente'] ?? []),
                     $_POST['contenido_html_custom'] ?? '',
                     $_POST['mostrar_cantidad_como'] ?? 'unidad',
                     isset($_POST['requiere_recoleccion']) ? 1 : 0,
                     isset($_POST['conversion_automatica']) ? 1 : 0,
                     isset($_POST['notificar_vistas_wa']) ? 1 : 0,
                     $formulario_id,
                     $formulario_config,
                     $_SESSION['user_id'],
                     $cot_id
                  ]);
                 
                  // Clear details for rewrite
                  $pdo->prepare("DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?")->execute([$cot_id]);
            }

            // Insert Details
            $stmtDetalle = $pdo->prepare("INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, nombre_producto, imagen, descripcion, cantidad, unidad_nombre, precio_unitario, impuesto_porcentaje, subtotal, seccion, es_opcional, seleccionado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!empty($_POST['productos'])) {
                foreach ($_POST['productos'] as $prod) {
                    if (empty($prod['nombre'])) continue;
                    $stmtDetalle->execute([
                        $cot_id,
                        !empty($prod['id']) ? $prod['id'] : null,
                        $prod['nombre'],
                        $prod['imagen'] ?? null,
                        $prod['descripcion'] ?? '',
                        $prod['cantidad'],
                        $prod['unidad'] ?? '',
                        $prod['precio'],
                        $prod['impuesto_p'] ?? 0,
                        $prod['subtotal'],
                        $prod['seccion'] ?? 'General',
                        isset($prod['es_opcional']) ? 1 : 0,
                        isset($prod['seleccionado']) ? 1 : 0
                    ]);
                }
            }
            
            // Increment numbering securely
            if ($action === 'insert') {
                if (isset($force_update_number)) {
                    // Update to specific calculated number
                    $pdo->prepare("UPDATE empresas SET starting_quote_number = ? WHERE id = ?")->execute([$force_update_number, getEmpresaId()]);
                } else {
                    // Standard increment
                    $pdo->prepare("UPDATE empresas SET starting_quote_number = starting_quote_number + 1 WHERE id = ?")->execute([getEmpresaId()]);
                }
            }

            $pdo->commit();
            header("Location: disenar-cotizacion.php?id=" . $cot_id); // Next Step
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Data Fetching
$empresa_id = getEmpresaId();
$stmt_emp = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt_emp->execute([$empresa_id]);
$empresa_config = $stmt_emp->fetch();

$sugerido = ($empresa_config['quote_prefix'] ?? '') . ($empresa_config['starting_quote_number'] ?? 1) . ($empresa_config['quote_suffix'] ?? '');

// Cargar Cotización para Edición si hay ID
$cot_id = $_GET['id'] ?? null;
$cot_existente = null;
$detalles_existentes = [];

if ($cot_id) {
    $stmt_c = $pdo->prepare("SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.identificacion as cliente_identificacion, cl.es_cliente, cl.nombre_contacto, cl.telefono as cliente_telefono, cl.celular_contacto as cliente_celular 
                             FROM cotizaciones c 
                             LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                             WHERE c.id = ? AND c.empresa_id = ?");
    $stmt_c->execute([$cot_id, $empresa_id]);
    $cot_existente = $stmt_c->fetch();

    if ($cot_existente) {
        $stmt_d = $pdo->prepare("SELECT cd.*, p.nombre as producto_nombre FROM cotizacion_detalles cd LEFT JOIN productos p ON cd.producto_id = p.id WHERE cd.cotizacion_id = ? ORDER BY cd.id ASC");
        $stmt_d->execute([$cot_id]);
        $detalles_existentes = $stmt_d->fetchAll();
        
        // Ajustar sugerido si editamos
        if (!empty($cot_existente['numero_cotizacion'])) {
            $sugerido = $cot_existente['numero_cotizacion'];
        }
    }
}

$todos = $pdo->prepare("SELECT * FROM clientes WHERE empresa_id = ? ORDER BY nombre ASC");
$todos->execute([$empresa_id]);
$todos = $todos->fetchAll();

$clientes_list = [];
$prospectos_list = [];
foreach ($todos as $t) {
    if ($t['es_cliente'] == 1) $clientes_list[] = $t;
    else $prospectos_list[] = $t;
}

// Unified selection variables
$selected_id = $cot_existente['cliente_id'] ?? ($_GET['cliente_id'] ?? '');
$label_type = 'cliente';
if ($selected_id) {
    foreach ($prospectos_list as $p) {
        if ($p['id'] == $selected_id) {
            $label_type = 'prospecto';
            break;
        }
    }
}
$clientes = $clientes_list; // Default for backward compatibility in first load

$plantillas = $pdo->prepare("SELECT id, nombre FROM plantillas WHERE empresa_id = ?");
$plantillas->execute([$empresa_id]);
$plantillas = $plantillas->fetchAll();

$productos = $pdo->prepare("SELECT * FROM productos WHERE empresa_id = ?");
$productos->execute([$empresa_id]);
$productos = $productos->fetchAll();

$impuestos_lista = $pdo->prepare("SELECT * FROM impuestos WHERE empresa_id = ?");
$impuestos_lista->execute([$empresa_id]);
$impuestos_lista = $impuestos_lista->fetchAll();

$unidades_lista = $pdo->prepare("SELECT * FROM unidades_medida WHERE empresa_id = ?");
$unidades_lista->execute([$empresa_id]);
$unidades_lista = $unidades_lista->fetchAll();

// Formularios Disponibles
$formularios = $pdo->prepare("SELECT id, titulo FROM formularios WHERE empresa_id = ? AND is_active = 1 ORDER BY titulo ASC");
$formularios->execute([$empresa_id]);
$formularios = $formularios->fetchAll();

// Tareas Post-Firma
$tareas_def = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tareas_empresa WHERE empresa_id = ? AND activo = 1 ORDER BY orden ASC");
    $stmt->execute([$empresa_id]);
    $tareas_def = $stmt->fetchAll();
} catch (Exception $e) {
    // Si la tabla no existe o hay error, continuamos sin tareas
    error_log("Error cargando tareas_empresa: " . $e->getMessage());
    $tareas_def = [];
}


include 'includes/header.php';
?>

<!-- Wizard CSS Customization -->
<!-- Wizard CSS Customization -->
<style>
    .wizard-step { display: none !important; }
    .wizard-step.active { display: block !important; animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .product-card.selected { border-color: #4f46e5 !important; background-color: #eef2ff !important; }

    /* Glassmorphism & Smart Search UI */
    .glass-effect {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .search-results-container {
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        position: absolute;
        width: 100%;
        z-index: 50;
        pointer-events: none; /* Prevent blocking clicks when invisible */
    }
    .search-results-container.active {
        max-height: 400px;
        opacity: 1;
        margin-top: 0.5rem;
        pointer-events: auto; /* Enable clicks when visible */
    }
    .type-toggle-btn.active {
        background-color: white;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        color: #2563eb;
    }
    .type-toggle-btn:not(.active) {
        color: #9ca3af;
    }
    .client-card-premium {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    .client-card-premium:hover {
        border-color: #3b82f6;
        box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1);
    }
</style>

<div class="max-w-6xl mx-auto px-4 pb-20 pt-6">
    
    <!-- Top Nav / Progress -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Nueva Cotización</h1>
            <p class="text-sm text-gray-500 font-medium">Crea una propuesta irresistible en 3 pasos.</p>
        </div>
        <div class="flex items-center gap-2">
           <div class="flex items-center">
               <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-lg shadow-blue-200">1</span>
               <span class="hidden md:block ml-2 text-sm font-bold text-gray-800">Datos</span>
           </div>
           <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
           <div class="flex items-center opacity-50" id="ind-step-2">
               <span class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-sm">2</span>
               <span class="hidden md:block ml-2 text-sm font-bold text-gray-800">Items</span>
           </div>
           <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
           <div class="flex items-center opacity-50" id="ind-step-3">
               <span class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-sm">3</span>
               <span class="hidden md:block ml-2 text-sm font-bold text-gray-800">Cierre</span>
           </div>
           
           <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
           <div class="flex items-center opacity-50" id="ind-step-4">
               <span class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-sm">4</span>
               <span class="hidden md:block ml-2 text-sm font-bold text-gray-800">Vista Previa</span>
           </div>
        </div>
    </div>

    <!-- Main Wizard Form -->
    <form action="nueva-cotizacion.php" method="POST" id="wizardForm" class="relative">
        <input type="hidden" name="cotizacion_id" value="<?php echo $cot_id; ?>">
        <input type="hidden" name="total_subtotal" id="input-subtotal" value="<?php echo $cot_existente['subtotal'] ?? 0; ?>">
        <input type="hidden" name="total_impuestos" id="input-impuestos" value="<?php echo $cot_existente['impuesto_total'] ?? ($cot_existente['impuestos'] ?? 0); ?>">
        <input type="hidden" name="total_general" id="input-total" value="<?php echo $cot_existente['total'] ?? 0; ?>">
        
        <?php if(!empty($cot_existente['plantilla_id'])): ?>
            <input type="hidden" name="plantilla_id" value="<?php echo $cot_existente['plantilla_id']; ?>">
        <?php else: ?>
             <!-- Default Template ID if any, or let user select. For now, we assume passed or default 1 -->
             <input type="hidden" name="plantilla_id" value="<?php echo $_GET['plantilla_id'] ?? 1; ?>">
        <?php endif; ?>

        <!-- STEP 1: CLIENT & BASICS -->
        <div id="step-1" class="wizard-step active">
            <div class="bg-white p-8 rounded-[2rem] border border-gray-100 shadow-xl space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Cliente Selector -->
                <div class="space-y-6">
                    <!-- Integrated Smart Search -->
                    <div class="space-y-3">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">¿Para quién es la cotización?</label>
                        
                        <input type="hidden" name="cliente_id" id="input-cliente-id" value="<?php echo $selected_id; ?>">
                        
                        <!-- Empty State: Smart Search Bar + Create Button -->
                        <div id="empty-client-card" class="space-y-4 relative <?php echo $selected_id ? 'hidden' : ''; ?>">
                            <div class="flex gap-2">
                                <div class="relative group flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input type="text" id="smart-search-input" placeholder="Buscar cliente o prospecto..." class="w-full pl-12 pr-20 py-4 rounded-2xl border border-gray-100 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-blue-50 outline-none transition-all font-bold text-gray-800 shadow-sm" oninput="handleSmartSearch(this.value)">
                                    
                                    <!-- Floating Type Toggle inside Search -->
                                    <div class="absolute right-2 top-1/2 -translate-y-1/2 bg-gray-100 p-1 rounded-xl flex gap-1 z-20">
                                        <button type="button" onclick="setSearchType('cliente')" class="type-toggle-btn px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-tighter transition-all active" id="toggle-cliente">Cliente</button>
                                        <button type="button" onclick="setSearchType('prospecto')" class="type-toggle-btn px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-tighter transition-all" id="toggle-prospecto">Prospecto</button>
                                    </div>
                                </div>
                                <button type="button" onclick="abrirModalCliente()" class="bg-blue-600 text-white p-4 rounded-2xl shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-105 transition-all flex items-center gap-2 group shrink-0" title="Registrar Nuevo">
                                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                     <span class="hidden xl:inline font-bold text-sm">Nuevo</span>
                                </button>
                            </div>

                            <!-- Results Dropdown (Glassmorphism) -->
                            <div id="smart-search-results" class="search-results-container bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-2xl overflow-hidden mt-2">
                                <div class="p-2 space-y-1" id="results-list">
                                    <!-- Results injected here -->
                                </div>
                            </div>
                        </div>

                        <!-- Selected State: Premium Client Card -->
                        <div id="selected-client-card" class="<?php echo $selected_id ? 'flex' : 'hidden'; ?> client-card-premium p-4 rounded-[2rem] items-center gap-4 relative overflow-hidden group"
                             data-email="<?php echo htmlspecialchars($cot_existente['cliente_email'] ?? ''); ?>"
                             data-wa="<?php echo htmlspecialchars($cot_existente['cliente_celular'] ?? $cot_existente['cliente_telefono'] ?? ''); ?>">
                            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-xl shadow-blue-200 shrink-0 transform group-hover:scale-105 transition-transform" id="selected-client-avatar">
                                <?php echo $selected_id ? substr($cot_existente['cliente_nombre'] ?? 'C',0,1) : 'C'; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 id="selected-client-name" class="font-black text-gray-900 text-xl truncate"><?php echo $selected_id ? htmlspecialchars($cot_existente['cliente_nombre'] ?? '') : ''; ?></h3>
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[10px] font-black uppercase rounded-lg border border-blue-100" id="selected-client-badge">
                                        <?php echo $label_type; ?>
                                    </span>
                                </div>
                                <p id="selected-client-detail" class="text-sm text-gray-500 font-medium truncate"><?php echo $selected_id ? htmlspecialchars($cot_existente['cliente_email'] ?? ($cot_existente['cliente_identificacion'] ?? '')) : ''; ?></p>
                            </div>
                            <button type="button" onclick="clearClientSelection()" class="p-3 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-2xl transition-all cursor-pointer">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                    <!-- Title Input -->
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Título del Proyecto / Cotización <span class="text-gray-300 font-normal normal-case">(Opcional)</span></label>
                        <div class="relative group">
                            <input type="text" name="titulo_cotizacion" value="<?php echo htmlspecialchars($cot_existente['titulo_cotizacion'] ?? ''); ?>" placeholder="Ej: Renovación de Equipos 2026..." class="w-full px-5 py-4 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold text-gray-800 text-base placeholder:font-normal placeholder:text-gray-400">
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Dates & Number -->
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Fecha Emisión</label>
                                <input type="date" name="fecha" value="<?php echo $cot_existente['fecha'] ?? date('Y-m-d'); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 font-bold outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Vence</label>
                                <input type="date" name="fecha_vencimiento" value="<?php echo $cot_existente['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+15 days')); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 font-bold outline-none focus:ring-2 focus:ring-blue-500 text-orange-600">
                            </div>
                        </div>
                        <div>
                             <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Número de Cotización</label>
                             <input type="text" name="numero_cotizacion" value="<?php echo htmlspecialchars($sugerido); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 font-bold outline-none focus:ring-2 focus:ring-blue-500 text-blue-600">
                             <p class="text-[10px] text-gray-400 mt-1">Sugerido automáticamente basado en tus ajustes.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end">
                <button type="button" onclick="goToStep(2)" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black text-lg shadow-xl shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center gap-2">
                    Continuar a Productos &rarr;
                </button>
            </div>
        </div>

        <!-- STEP 2: PRODUCTS (VISUAL GRID) -->
        <div id="step-2" class="wizard-step">
            <div class="flex flex-col lg:flex-row gap-8 items-start">
                
                <!-- Product Catalog (Left - Smaller now) -->
                <div class="w-full lg:w-1/3 space-y-4">
                    <div class="bg-white p-6 rounded-[2rem] border border-gray-100 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">Catálogo</h2>
                        <input type="text" placeholder="Buscar item..." class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-blue-500 mb-4">
                        
                        <div class="grid grid-cols-2 gap-3 max-h-[500px] overflow-y-auto pr-1 custom-scroll">
                            <div onclick="addCustomItem()" class="cursor-pointer border-2 border-dashed border-gray-200 rounded-2xl p-4 flex flex-col items-center justify-center text-center hover:border-blue-400 hover:bg-blue-50 transition-all min-h-[120px] group bg-gray-50/50">
                                <span class="text-xs font-black uppercase text-gray-400 group-hover:text-blue-600">+ Nuevo Item</span>
                            </div>

                            <?php foreach($productos as $p): ?>
                            <div onclick='addItem(<?php echo json_encode($p); ?>)' class="cursor-pointer bg-white border border-gray-100 rounded-2xl p-2 hover:shadow-md transition-all group relative">
                                <div class="h-16 bg-gray-50 rounded-lg mb-2 overflow-hidden flex items-center justify-center">
                                    <?php if($p['imagen']): ?>
                                        <img src="<?php echo $p['imagen']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-bold text-gray-800 text-xs truncate"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                                <p class="text-indigo-600 font-bold text-xs">$<?php echo number_format($p['precio_base'], 0); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Selected Items (Right - WIDER now) -->
                <div class="w-full lg:flex-1 bg-white border border-gray-200 shadow-2xl rounded-[2rem] flex flex-col min-h-[600px] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                    
                    <div class="p-8 border-b border-gray-100 flex justify-between items-end">
                        <div>
                            <h3 class="font-black text-gray-900 text-2xl">Cotización Actual</h3>
                            <p class="text-sm text-gray-400 font-medium mt-1">Arrastra items o haz click en el catálogo.</p>
                        </div>
                        <div class="text-right hidden md:block">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total (Opción Principal)</p>
                            <p class="text-2xl font-black text-indigo-600" id="header-total">$0.00</p>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-6 space-y-8 custom-scroll bg-gray-50/30" id="selected-items-container">
                        <!-- Sections Container -->
                        <div id="sections-wrapper" class="space-y-8">
                            <!-- Default Section will be created by JS -->
                        </div>
                        
                        <div class="mt-8 flex flex-col items-center gap-4">
                            <button type="button" onclick="createNewSection()" class="bg-gray-50 text-gray-500 hover:text-blue-600 hover:bg-blue-50 border-2 border-dashed border-gray-200 hover:border-blue-200 font-bold py-4 px-8 rounded-2xl w-full transition-all flex items-center justify-center gap-2 group">
                                <span class="bg-gray-200 text-gray-400 group-hover:bg-blue-600 group-hover:text-white rounded-full w-6 h-6 flex items-center justify-center transition-colors">+</span>
                                Agregar Nueva Sección / Opción
                            </button>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="check-sum-all" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" onchange="toggleSumAll(this)">
                                <label for="check-sum-all" class="text-xs font-bold text-gray-400 uppercase tracking-widest cursor-pointer hover:text-gray-600">Sumar todas las opciones al Total Final</label>
                            </div>
                        </div>

                        <div id="empty-state" class="hidden h-full flex flex-col items-center justify-center text-gray-300 opacity-50 py-20">
                            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                            <p class="text-lg font-bold">El carrito está vacío</p>
                            <p class="text-sm">Selecciona productos para comenzar.</p>
                        </div>
                    </div>

                    <div class="p-8 bg-white border-t border-gray-100 rounded-b-[2rem]">
                        <div class="flex flex-col md:flex-row justify-end gap-12">
                            <div class="space-y-1 text-right">
                                <span class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Subtotal</span>
                                <span class="block text-lg font-bold text-gray-900" id="mini-subtotal">$0.00</span>
                            </div>
                            <div class="space-y-1 text-right">
                                <span class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Impuestos</span>
                                <span class="block text-lg font-bold text-gray-900" id="mini-impuestos">$0.00</span>
                            </div>
                            <div class="space-y-1 text-right">
                                <span class="block text-xs font-black text-gray-400 uppercase tracking-widest">Total Final</span>
                                <span class="block text-3xl font-black text-indigo-600" id="mini-total">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-between p-4 bg-white rounded-2xl shadow-sm border border-gray-100 sticky bottom-4 z-10 md:static md:shadow-none md:border-0 md:bg-transparent">
                <button type="button" onclick="goToStep(1)" class="text-gray-500 font-bold hover:text-gray-800 transition-all flex items-center gap-2">
                    &larr; Volver
                </button>
                <div class="flex flex-col md:flex-row items-end md:items-center gap-4">
                    <span class="md:hidden font-black text-indigo-600 text-lg" id="mobile-total">$0.00</span>
                    <button type="button" onclick="goToStep(3)" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-black shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center gap-2">
                        Siguiente paso &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 3: CLOSE & CONFIG -->
        <div id="step-3" class="wizard-step">
            <!-- (Content unchanged, keeping existing step 3) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Actions -->
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-lg">
                        <!-- Dynamic Tasks -->
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">Diseño y Presentación</h4>
                        <div class="space-y-4 mb-6">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Selecciona una Plantilla</label>
                            <select name="plantilla_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                                <option value="">Diseño Premium (Por defecto)</option>
                                <?php 
                                $selected_plantilla = $cot_existente['plantilla_id'] ?? '';
                                foreach($plantillas as $pl): ?>
                                    <option value="<?php echo $pl['id']; ?>" <?php echo ($selected_plantilla == $pl['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">Tareas Post-Aceptación</h4>
                        <div class="space-y-3">
                             <?php 
                             $tareas_actuales = json_decode($cot_existente['config_pasos'] ?? '[]', true);
                             if(empty($tareas_def)): ?>
                                <p class="text-xs text-gray-400 italic">No hay tareas definidas. <a href="configurar-tareas.php" target="_blank" class="text-blue-500 hover:underline">Configurar aquí</a></p>
                             <?php else: foreach($tareas_def as $t): 
                                 $isChecked = in_array($t['nombre'], $tareas_actuales) ? 'checked' : '';
                             ?>
                                 <label class="flex items-start gap-3 p-3 bg-green-50 rounded-xl cursor-pointer border border-green-100 hover:bg-green-100 transition-all">
                                    <input type="checkbox" name="pasos_siguientes[]" value="<?php echo htmlspecialchars($t['nombre']); ?>" <?php echo $isChecked; ?> class="w-5 h-5 text-green-600 rounded focus:ring-green-500 mt-0.5">
                                    <div>
                                        <span class="block text-sm font-bold text-green-900"><?php echo htmlspecialchars($t['nombre']); ?></span>
                                         <span class="block text-xs text-green-700"><?php echo htmlspecialchars($t['descripcion']); ?></span>
                                     </div>
                                  </label>
                              <?php endforeach; endif; ?>
                        </div>

                        <!-- NUEVA SECCION: Configuración Cliente (Conversión & Documentos) -->
                        <div class="mt-8 pt-6 border-t border-gray-100">
                            <h4 class="text-xs font-black text-blue-400 uppercase tracking-widest mb-3">Requisitos para el Cliente</h4>
                            
                            <!-- Conversion Automatica -->
                            <div class="mb-4">
                                <label class="flex items-center justify-between p-3 bg-blue-50/50 rounded-xl cursor-pointer hover:bg-blue-50 transition-all border border-blue-100">
                                    <div>
                                        <span class="block text-sm font-bold text-gray-800">Conversión Automática</span>
                                        <span class="block text-[10px] text-gray-500">¿Convertir prospecto a CLIENTE al firmar?</span>
                                    </div>
                                    <div class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="conversion_automatica" value="1" class="sr-only peer" <?php echo ($cot_existente['conversion_automatica'] ?? 1) ? 'checked' : ''; ?>>
                                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </div>
                                </label>
                            </div>

                            <!-- Notificación WA al creador -->
                            <div class="mb-4">
                                <label class="flex items-center justify-between p-3 bg-green-50/50 rounded-xl cursor-pointer hover:bg-green-50 transition-all border border-green-100">
                                    <div>
                                        <span class="block text-sm font-bold text-gray-800">Notificar Visto al WhatsApp</span>
                                        <span class="block text-[10px] text-gray-500">¿Recibir alertas cuando el cliente abra el enlace?</span>
                                    </div>
                                    <div class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="notificar_vistas_wa" value="1" class="sr-only peer" <?php echo (!isset($cot_existente) || ($cot_existente['notificar_vistas_wa'] ?? 0)) ? 'checked' : ''; ?>>
            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
        </div>                            </div>
                                </label>
                            </div>


                             <!-- NUEVO: Integración de Formularios -->
                             <h4 class="text-xs font-black text-blue-400 uppercase tracking-widest mb-3">Formulario Requerido</h4>
                             <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Seleccionar Formulario</label>
                                    <select name="formulario_id" onchange="triggerAutoSave()" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                                        <option value="">Ninguno</option>
                                        <?php 
                                        $f_actual = $cot_existente['formulario_id'] ?? '';
                                        foreach($formularios as $f): ?>
                                            <option value="<?php echo $f['id']; ?>" <?php echo ($f_actual == $f['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['titulo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Modo de Integración</label>
                                    <select name="formulario_modo" onchange="triggerAutoSave()" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                                        <?php 
                                        $f_config = json_decode($cot_existente['formulario_config'] ?? '{}', true);
                                        // $modo_actual = $f_config['modo'] ?? 'ninguno'; // OLD logic
                                        
                                        // NEW LOGIC: If form is selected, we suggest 'reemplazar_firma' by default or enforce it
                                        // But for the dropdown, we show what's saved.
                                        $modo_actual = $f_config['modo'] ?? 'ninguno';
                                        ?>
                                        <option value="ninguno" <?php echo ($modo_actual == 'ninguno') ? 'selected' : ''; ?>>Opcional (Mostrar enlace)</option>
                                        <option value="antes_firma" <?php echo ($modo_actual == 'antes_firma') ? 'selected' : ''; ?>>Requerido antes de firmar</option>
                                        <option value="despues_firma" <?php echo ($modo_actual == 'despues_firma') ? 'selected' : ''; ?>>Solicitar después de firmar</option>
                                        <option value="reemplazar_firma" <?php echo ($modo_actual == 'reemplazar_firma') ? 'selected' : ''; ?>>Sustituir firma por Formulario</option>
                                    </select>
                                    <p class="text-[10px] text-gray-400 mt-1 leading-tight">Define en qué momento el cliente debe completar el formulario durante la aceptación.</p>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-lg h-full">
                    <h3 class="font-bold text-gray-800 mb-4">Términos y Observaciones Públicas</h3>
                    <textarea name="notas" class="w-full h-[200px] p-4 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-medium" placeholder="Describe condiciones de pago, garantías, tiempos de entrega..."><?php echo htmlspecialchars($cot_existente['notas'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="mt-8 flex justify-between items-center">
                 <button type="button" onclick="goToStep(2)" class="bg-gray-100 text-gray-500 px-6 py-4 rounded-2xl font-bold hover:bg-gray-200 transition-all">
                    &larr; Atrás
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="triggerAutoSave(); goToStep(4)" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-10 py-4 rounded-2xl font-black text-xl shadow-2xl hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3">
                        Vista Previa y Enviar &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 4: DESIGN & PREVIEW -->
        <div id="step-4" class="wizard-step">
            <div class="bg-white rounded-[2rem] border border-gray-100 shadow-2xl h-[85vh] flex flex-col overflow-hidden relative">
                
                <!-- Toolbar Header -->
                <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-white z-20">
                    <div class="flex items-center gap-4">
                        <button type="button" onclick="goToStep(3)" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500 transition-all" title="Volver a Configuración">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        </button>
                        
                        <!-- View Switcher -->
                        <div class="flex bg-gray-100 p-1 rounded-xl">
                            <button type="button" onclick="switchPreviewMode('editor')" id="btn-mode-editor" class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all bg-white shadow-sm text-blue-600 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Editor
                            </button>
                            <button type="button" onclick="switchPreviewMode('preview')" id="btn-mode-preview" class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all text-gray-500 hover:text-gray-900 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Vista Previa
                            </button>
                        </div>
                    </div>

                    <!-- Shortcodes Bar (Editor Only) -->
                    <div id="toolbar-editor" class="hidden md:flex items-center gap-2 overflow-x-auto no-scrollbar max-w-4xl bg-gray-50/50 p-1 rounded-xl border border-gray-100">
                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap px-2">Empresa:</span>
                        <div class="flex gap-1">
                            <button type="button" onclick="insertarTag('{LOGO}')" class="px-2 py-1 bg-white hover:bg-gray-100 rounded text-[9px] font-bold text-gray-600 border border-gray-200" title="Logo de tu empresa">{LOGO}</button>
                            <button type="button" onclick="insertarTag('{EMPRESA}')" class="px-2 py-1 bg-white hover:bg-gray-100 rounded text-[9px] font-bold text-gray-600 border border-gray-200" title="Nombre de tu empresa">{EMPRESA}</button>
                            <button type="button" onclick="insertarTag('{CONTACTO_TEL}')" class="px-2 py-1 bg-white hover:bg-gray-100 rounded text-[9px] font-bold text-gray-600 border border-gray-200" title="Teléfono de contacto">{TEL}</button>
                        </div>
                        
                        <div class="h-4 w-px bg-gray-200 mx-1"></div>
                        
                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest whitespace-nowrap px-1">Cotización:</span>
                        <div class="flex gap-1">
                            <button type="button" onclick="insertarTag('{NUMERO_COT}')" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 rounded text-[9px] font-bold text-indigo-600 border border-indigo-200">{NUMERO}</button>
                            <button type="button" onclick="insertarTag('{TITULO_PROYECTO}')" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 rounded text-[9px] font-bold text-indigo-600 border border-indigo-200">{TITULO}</button>
                            <button type="button" onclick="insertarTag('{CLIENTE}')" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 rounded text-[9px] font-bold text-indigo-600 border border-indigo-200">{CLIENTE}</button>
                        </div>

                        <div class="h-4 w-px bg-gray-200 mx-1"></div>

                        <span class="text-[9px] font-black text-green-400 uppercase tracking-widest whitespace-nowrap px-1">Valores:</span>
                        <div class="flex gap-1">
                            <button type="button" onclick="insertarTag('{SUBTOTAL}')" class="px-2 py-1 bg-green-50 hover:bg-green-100 rounded text-[9px] font-bold text-green-600 border border-green-200">{SUB}</button>
                            <button type="button" onclick="insertarTag('{IMPUESTOS}')" class="px-2 py-1 bg-green-50 hover:bg-green-100 rounded text-[9px] font-bold text-green-600 border border-green-200">{IVA}</button>
                            <button type="button" onclick="insertarTag('{TOTAL}')" class="px-2 py-1 bg-green-600 hover:bg-green-700 rounded text-[9px] font-bold text-white border border-green-700">{TOTAL}</button>
                        </div>

                        <div class="h-4 w-px bg-gray-200 mx-1"></div>

                        <span class="text-[9px] font-black text-blue-400 uppercase tracking-widest whitespace-nowrap px-1">Bloques:</span>
                        <div class="flex gap-1">
                            <button type="button" onclick="insertarTag('{TABLA_PRECIOS}')" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded text-[9px] font-bold text-blue-600 border border-blue-200" title="Tabla detallada de items">{TABLA}</button>
                            <button type="button" onclick="insertarTag('{NOTAS}')" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded text-[9px] font-bold text-blue-600 border border-blue-200" title="Observaciones públicas">{NOTAS}</button>
                            <button type="button" onclick="insertarTag('{REDES_SOCIALES}')" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded text-[9px] font-bold text-blue-600 border border-blue-200" title="Iconos de redes sociales">{REDES}</button>
                            <button type="button" onclick="insertarTag('{LOGOS_CLIENTES}')" class="px-2 py-1 bg-blue-50 hover:bg-blue-100 rounded text-[9px] font-bold text-blue-600 border border-blue-200" title="Logos de empresas que confían en ti">{CONFIAN}</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" onclick="saveDraftContent()" id="btn-save-draft" class="px-4 py-2 bg-gray-100 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-200 transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                            <span class="hidden md:inline">Guardar</span>
                        </button>
                        <button type="button" onclick="confirmAndSend()" class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-black rounded-xl shadow-lg hover:shadow-green-500/30 transition-all flex items-center gap-2 text-xs uppercase tracking-widest">
                             <span>Enviar Ahora</span>
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 relative bg-slate-100 overflow-hidden">
                    
                    <!-- EDITOR AREA -->
                    <div id="view-editor" class="absolute inset-0 flex flex-col p-6 overflow-y-auto">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex-1 flex flex-col overflow-hidden">
                            <textarea id="editor-html" name="contenido_html_custom" class="flex-1 w-full p-6 font-mono text-sm text-slate-700 outline-none resize-none bg-white leading-relaxed" spellcheck="false" placeholder="Cargando contenido de la plantilla..."></textarea>
                        </div>
                    </div>

                    <!-- PREVIEW AREA -->
                    <div id="view-preview" class="absolute inset-0 hidden bg-slate-500/10 flex flex-col">
                        <div class="flex-1 relative w-full h-full">
                            <div class="absolute inset-0 flex items-center justify-center z-0" id="preview-loader">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                            </div>
                            <iframe id="preview-frame" class="relative z-10 w-full h-full border-0 opacity-0 transition-opacity duration-300" onload="this.classList.remove('opacity-0'); document.getElementById('preview-loader').style.display='none';"></iframe>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Crear Cliente -->
<div id="modalNuevoCliente" class="fixed inset-0 z-[200] hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModalCliente()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up">
        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-black text-gray-900">Nuevo Cliente</h3>
            <button onclick="cerrarModalCliente()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="formNuevoCliente" onsubmit="crearCliente(event)" class="p-8 space-y-4">
            <input type="hidden" name="es_cliente" id="input-new-es-cliente" value="1">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nombre / Razón Social</label>
                <input type="text" name="nombre" required class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Identificación / NIT</label>
                     <input type="text" name="identificacion" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">País</label>
                    <select name="pais_codigo" id="modalInputPais" onchange="updateModalCountryCode(this.value)" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="57">Colombia (+57)</option>
                        <option value="1">USA (+1)</option>
                        <option value="34">España (+34)</option>
                        <option value="52">México (+52)</option>
                        <option value="54">Argentina (+54)</option>
                        <option value="56">Chile (+56)</option>
                        <option value="51">Perú (+51)</option>
                        <option value="593">Ecuador (+593)</option>
                        <option value="507">Panamá (+507)</option>
                    </select>
                </div>
            </div>

            <div class="bg-blue-50/50 p-5 rounded-2xl border border-blue-100 space-y-3">
                <label class="block text-[10px] font-black text-blue-400 uppercase tracking-widest">Información de Contacto</label>
                <div class="relative">
                    <span id="modalLabelCountryCode" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">+57</span>
                    <input type="text" name="celular_contacto" id="modalInputCelular" placeholder="3001234567" class="w-full pl-14 pr-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                </div>
                <input type="text" name="telefono" id="modalInputTelefono" placeholder="Teléfono Secundario" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500 bg-white text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Email</label>
                <input type="email" name="email" id="modalInputEmail" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mt-8 flex gap-3">
                <button type="button" onclick="cerrarModalCliente()" class="flex-1 py-4 font-bold text-gray-500 hover:bg-gray-50 rounded-2xl">Cancelar</button>
                <button type="submit" class="flex-1 py-4 bg-blue-600 text-white font-black rounded-2xl shadow-lg hover:bg-blue-700 active:scale-95 transition-all">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Enviar Cotización -->
<div id="modalSendQuote" class="fixed inset-0 z-[200] hidden">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="cerrarModalSend()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-white rounded-[2.5rem] overflow-hidden shadow-2xl animate-fade-in-up border border-gray-100">
        <div class="bg-gray-50/50 p-8 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h3 class="text-2xl font-black text-gray-900">Enviar Propuesta</h3>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Configura los destinatarios y medios de envío</p>
            </div>
            <button onclick="cerrarModalSend()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="p-8 space-y-8 max-h-[70vh] overflow-y-auto custom-scroll">
            <!-- Resumen Cliente -->
            <div class="bg-blue-50/50 border border-blue-100 rounded-3xl p-6 flex items-center gap-6">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-xl shadow-blue-200" id="send-avatar">C</div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-black text-gray-900 text-xl truncate" id="send-client-company">Empresa Cliente</h4>
                    <p class="text-sm text-gray-500 font-medium" id="send-client-contact">Nombre del Contacto</p>
                    <div class="flex gap-4 mt-2">
                        <span class="text-[10px] font-bold text-blue-600 bg-blue-100 px-2 py-0.5 rounded-lg" id="send-client-email-badge">Email</span>
                        <span class="text-[10px] font-bold text-green-600 bg-green-100 px-2 py-0.5 rounded-lg" id="send-client-wa-badge">WhatsApp</span>
                    </div>
                </div>
            </div>

            <!-- Destinatarios EMAIL -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Enviar por Correo Electrónico</h4>
                    <button type="button" onclick="addRecipientRow('email')" class="text-[10px] font-black text-blue-600 hover:text-blue-700 uppercase tracking-widest">+ Agregar otro email</button>
                </div>
                <div id="email-recipients-list" class="space-y-3">
                    <!-- Default client email row -->
                    <div class="flex gap-2 group">
                        <div class="relative flex-1">
                            <input type="email" name="send_emails[]" id="primary-email" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500 transition-all text-sm font-bold" placeholder="correo@ejemplo.com">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-blue-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Destinatarios WHATSAPP -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Enviar por WhatsApp</h4>
                    <button type="button" onclick="addRecipientRow('whatsapp')" class="text-[10px] font-black text-green-600 hover:text-green-700 uppercase tracking-widest">+ Agregar otro WhatsApp</button>
                </div>
                <div id="wa-recipients-list" class="space-y-3">
                    <!-- Default client WA row -->
                    <div class="flex gap-2 group">
                        <div class="w-32">
                            <select name="send_wa_countries[]" class="w-full px-3 py-3 rounded-xl border border-gray-200 bg-gray-50/50 text-xs font-bold outline-none focus:bg-white transition-all appearance-none">
                                <option value="57">🇨🇴 +57</option>
                                <option value="1">🇺🇸 +1</option>
                                <option value="34">🇪🇸 +34</option>
                                <option value="52">🇲🇽 +52</option>
                                <option value="54">🇦🇷 +54</option>
                                <option value="56">🇨🇱 +56</option>
                                <option value="51">🇵🇪 +51</option>
                                <option value="593">🇪🇨 +593</option>
                                <option value="507">🇵🇦 +507</option>
                            </select>
                        </div>
                        <div class="relative flex-1">
                            <input type="text" name="send_wa_numbers[]" id="primary-wa" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 outline-none focus:bg-white focus:ring-2 focus:ring-green-500 transition-all text-sm font-bold" placeholder="3001234567">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-green-500">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.27 9.27 0 01-4.487-1.164l-.322-.19-3.338.875.89-3.251-.208-.332A9.28 9.28 0 012.225 9.37C2.228 4.225 6.42 0 11.57 0a11.5 11.5 0 018.175 3.385 11.455 11.455 0 013.387 8.19c-.002 5.143-4.194 9.366-9.345 9.366z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mensaje Personalizado -->
            <div class="space-y-2">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">Mensaje del Envío (Opcional)</label>
                <textarea id="send-custom-message" class="w-full px-5 py-4 rounded-3xl border border-gray-200 bg-gray-50 outline-none focus:bg-white focus:ring-2 focus:ring-blue-500 transition-all text-sm font-medium h-32 resize-none" placeholder="Escribe un mensaje corto que acompañará la cotización..."></textarea>
            </div>
        </div>

        <div class="p-8 bg-gray-50 flex gap-4">
            <button type="button" onclick="cerrarModalSend()" class="flex-1 py-4 font-bold text-gray-500 hover:bg-white hover:shadow-sm rounded-2xl transition-all border border-transparent hover:border-gray-100">Cancelar</button>
            <button type="button" onclick="executeSending()" id="btn-final-send" class="flex-[2] py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-blue-200 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                Confirmar y Enviar Ahora
            </button>
        </div>
    </div>
</div>

<!-- Scripts for Wizard Logic -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputCelular = document.getElementById('modalInputCelular');
        const inputTelefono = document.getElementById('modalInputTelefono');
        const inputEmail = document.getElementById('modalInputEmail');

        if (inputCelular && inputTelefono) {
            inputCelular.addEventListener('input', () => {
                if (!inputTelefono.value || inputTelefono.value === inputCelular.value.substring(0, inputCelular.value.length - 1)) {
                    inputTelefono.value = inputCelular.value;
                }
            });
            inputCelular.addEventListener('blur', function() {
                checkDuplicate('telefono', this.value);
            });
        }

        if (inputEmail) {
            inputEmail.addEventListener('blur', function() {
                checkDuplicate('email', this.value);
            });
        }
    });

    async function checkDuplicate(type, value) {
        if (!value) return;
        // No exclude ID for new creation
        try {
            const response = await fetch(`api_check_duplicate.php?${type}=${encodeURIComponent(value)}`);
            const data = await response.json();
            
            if (data.exists) {
                alert('⚠️ ATENCIÓN: ' + data.message);
                const input = type === 'email' ? document.getElementById('modalInputEmail') : document.getElementById('modalInputCelular');
                input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                input.focus();
            } else {
                const input = type === 'email' ? document.getElementById('modalInputEmail') : document.getElementById('modalInputCelular');
                input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                input.classList.add('border-green-500');
            }
        } catch (e) {
            console.error('Error verificando duplicados:', e);
        }
    }

    function updateModalCountryCode(code) {
        document.getElementById('modalLabelCountryCode').innerText = '+' + code;
    }

    let itemCount = 0;
    let sectionCount = 0;
    let activeSectionId = null; // ID of the section currently receiving items
    const taxes = <?php echo json_encode($impuestos_lista) ?: '[]'; ?>;

    function goToStep(step) {
        // Validate Step 1
        if(step > 1) {
            const clientInput = document.getElementById('input-cliente-id');
            const client = clientInput ? clientInput.value : null;
            if(!client) { alert("Por favor selecciona un cliente."); return; }
        }

        document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
        const targetStep = document.getElementById('step-' + step);
        if(targetStep) targetStep.classList.add('active');
        
        // Scroll top
        window.scrollTo({top: 0, behavior: 'smooth'});
        
        // Update indicators
        for(let i=2; i<=4; i++) {
             const ind = document.getElementById('ind-step-' + i);
             if(!ind) continue;
             
             if(step >= i) {
                 ind.classList.remove('opacity-50');
                 const badge = ind.querySelector('span:first-child');
                 if(badge) {
                    badge.classList.remove('bg-gray-200', 'text-gray-600');
                    badge.classList.add('bg-blue-600', 'text-white');
                 }
             }
        }
        
        if(step === 4) {
             if(typeof loadPreviewStep === 'function') {
                 loadPreviewStep();
             }
        }
    }

    function switchMobileTab(tab) {
        const catalogView = document.getElementById('mobile-catalog-view');
        const cartView = document.getElementById('mobile-cart-view');
        const tabCatalog = document.getElementById('tab-catalog');
        const tabCart = document.getElementById('tab-cart');

        if(tab === 'catalog') {
            if(catalogView) catalogView.classList.remove('hidden');
            if(cartView) cartView.classList.add('hidden');
            // Update Tab Styles
            if(tabCatalog) tabCatalog.classList.add('bg-white', 'shadow-sm', 'text-blue-600');
            if(tabCatalog) tabCatalog.classList.remove('text-gray-500');
            if(tabCart) tabCart.classList.remove('bg-white', 'shadow-sm', 'text-blue-600');
            if(tabCart) tabCart.classList.add('text-gray-500');
        } else {
            if(catalogView) catalogView.classList.add('hidden');
            if(cartView) {
                cartView.classList.remove('hidden');
                cartView.classList.add('flex');
            }
            // Update Tab Styles
            if(tabCart) tabCart.classList.add('bg-white', 'shadow-sm', 'text-blue-600');
            if(tabCart) tabCart.classList.remove('text-gray-500');
            if(tabCatalog) tabCatalog.classList.remove('bg-white', 'shadow-sm', 'text-blue-600');
            if(tabCatalog) tabCatalog.classList.add('text-gray-500');
        }
    }

    // Modal Logic
    function abrirModalCliente() {
        const isClient = window.currentClientType === 'cliente' ? 1 : 0;
        document.getElementById('input-new-es-cliente').value = isClient;
        document.getElementById('modalNuevoCliente').classList.remove('hidden');
    }

    function cerrarModalCliente() {
        document.getElementById('modalNuevoCliente').classList.add('hidden');
    }

    async function crearCliente(e) {
        e.preventDefault();
        const form = document.getElementById('formNuevoCliente');
        const formData = new FormData(form);
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const res = await fetch('api_save_client.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                // Update new UI instead of select
                if (typeof window.selectClient === 'function') {
                    window.selectClient(data.data.id, data.data.nombre, data.data.identificacion || '', data.data.email || '');
                } else {
                    // Fallback directly
                     document.getElementById('input-cliente-id').value = data.data.id;
                     document.getElementById('selected-client-name').textContent = data.data.nombre;
                     document.getElementById('selected-client-card').classList.remove('hidden');
                     document.getElementById('selected-client-card').classList.add('flex');
                     document.getElementById('empty-client-card').classList.add('hidden');
                }
                
                cerrarModalCliente();
                form.reset();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // Initialize Default Section or Load Existing Items
    document.addEventListener('DOMContentLoaded', () => {
        const detallesExistentes = <?php echo json_encode($detalles_existentes) ?: '[]'; ?>;
        const stepRequested = <?php echo json_encode($_GET['step'] ?? 1) ?: '1'; ?>;
        
        if (detallesExistentes && detallesExistentes.length > 0) {
            // Group items by section
            const sections = {};
            detallesExistentes.forEach(d => {
                const sName = d.seccion || 'General';
                if (!sections[sName]) sections[sName] = [];
                sections[sName].push(d);
            });

            // Reconstruct sections and items
            let isFirst = true;
            for (const sName in sections) {
                createNewSection(sName, isFirst);
                sections[sName].forEach(item => {
                    // Match format expected by addItem
                    const prod = {
                        id: item.producto_id,
                        nombre: item.nombre_producto,
                        descripcion: item.descripcion,
                        precio_base: item.precio_unitario,
                        imagen: item.imagen,
                        porcentaje: item.impuesto_porcentaje,
                        unidad_nombre: item.unidad_nombre
                    };
                    addItem(prod);
                    
                    // Restore item-specific values
                    const lastIdx = itemCount - 1;
                    const row = document.getElementById(`item-row-${lastIdx}`);
                    if (row) {
                        const qtyInp = row.querySelector(`input[name="productos[${lastIdx}][cantidad]"]`);
                        if (qtyInp) qtyInp.value = item.cantidad;
                        
                        const opInp = row.querySelector(`input[name="productos[${lastIdx}][es_opcional]"]`);
                        if(opInp && item.es_opcional == 1) opInp.checked = true;

                        const selInp = row.querySelector(`input[name="productos[${lastIdx}][seleccionado]"]`);
                        if(selInp && item.seleccionado == 1) selInp.checked = true;

                        recalcRow(lastIdx);
                    }
                });
                isFirst = false;
            }
        } else {
            createNewSection('General', true);
        }

        if (stepRequested > 1) {
            goToStep(parseInt(stepRequested));
        }
    });

    function createNewSection(name = null, isDefault = false) {
        if (!name && !isDefault) {
            name = prompt("Nombre de la nueva sección/opción:", "Opción " + (sectionCount + 1));
            if (!name) return;
        }
        
        const secId = 'section-' + sectionCount++;
        activeSectionId = secId; // Set as active
        
        const sectionHtml = `
            <div id="${secId}" class="section-block bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in transition-all">
                <div class="bg-gray-50 p-4 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-100 transition-colors" onclick="setActiveSection('${secId}')">
                    <div class="flex items-center gap-3">
                        <div class="w-1 h-6 bg-blue-500 rounded-full indicator-active opacity-0 transition-opacity"></div>
                        <input type="text" value="${name}" class="bg-transparent font-black text-gray-700 outline-none focus:ring-2 focus:ring-blue-200 rounded px-2" onchange="updateSectionName('${secId}', this.value)">
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest section-total">$0.00</span>
                        ${!isDefault ? `<button onclick="removeSection('${secId}')" class="text-gray-400 hover:text-red-500 p-1 rounded-full hover:bg-red-50 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>` : ''}
                    </div>
                </div>
                <div class="p-4 space-y-3 section-items-container min-h-[50px]">
                    <!-- Items go here -->
                </div>
                <div class="p-2 bg-gray-50/50 border-t border-gray-50 text-center">
                    <button type="button" onclick="setActiveSection('${secId}'); document.getElementById('tab-catalog').click();" class="text-xs font-bold text-blue-500 hover:text-blue-700 py-1 px-3 rounded-lg hover:bg-blue-50 transition-all">
                        + Agregar Productos a esta sección
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('sections-wrapper').insertAdjacentHTML('beforeend', sectionHtml);
        updateActiveIndicator(secId);
    }

    function setActiveSection(id) {
        activeSectionId = id;
        updateActiveIndicator(id);
    }
    
    function updateActiveIndicator(id) {
        document.querySelectorAll('.indicator-active').forEach(el => el.classList.add('opacity-0'));
        const sec = document.getElementById(id);
        if(sec) {
            const ind = sec.querySelector('.indicator-active');
            if(ind) ind.classList.remove('opacity-0');
            sec.classList.add('ring-2', 'ring-blue-100');
            setTimeout(() => sec.classList.remove('ring-2', 'ring-blue-100'), 500); // Pulse effect
        }
    }

    function removeSection(id) {
        if(confirm('¿Eliminar esta sección y sus productos?')) {
            document.getElementById(id).remove();
            recalcTotal();
            // Reset active to first available
            const first = document.querySelector('.section-block');
            if(first) setActiveSection(first.id);
        }
    }

    function updateSectionName(id, val) {
        // Just for UI persistence if needed, usually input value is enough
    }

    function addCustomItem() {
        const item = {
            id: null,
            nombre: "Item Nuevo",
            descripcion: "",
            precio_base: 0,
            imagen: null,
            porcentaje: 0 // Default tax
        };
        addItem(item, true);
    }

    function addItem(product, isCustom = false) {
        if(!activeSectionId) {
            // Try to find first section
            const first = document.querySelector('.section-block');
            if(first) activeSectionId = first.id;
            else {
                createNewSection('General', true); // Should have been created but just in case
            }
        }

        const sectionEl = document.getElementById(activeSectionId);
        if(!sectionEl) {
            // Fallback
            createNewSection('General', true);
        }
        
        const container = document.getElementById(activeSectionId).querySelector('.section-items-container');
        const sectionName = document.getElementById(activeSectionId).querySelector('input').value; // Get current name
        
        const idx = itemCount++;
        
        // Build Tax Options
        let taxOptions = `<option value="0">Sin Impuesto</option>`;
        taxes.forEach(t => {
            const isSelected = (parseFloat(product.impuesto_id) == t.id) || (parseFloat(product.porcentaje) == parseFloat(t.porcentaje)) ? 'selected' : '';
            taxOptions += `<option value="${t.porcentaje}" ${isSelected}>${t.nombre} (${t.porcentaje}%)</option>`;
        });

        const itemHtml = `
        <div id="item-row-${idx}" class="item-row bg-white p-3 rounded-xl border border-gray-100 flex gap-3 group relative hover:border-blue-300 transition-all">
            <input type="hidden" name="productos[${idx}][seccion]" value="${sectionName}" class="section-name-input">
            
            <div class="w-12 h-12 bg-gray-50 rounded-lg overflow-hidden flex items-center justify-center shrink-0 border border-gray-100">
                ${product.imagen ? `<img src="${product.imagen}" class="w-full h-full object-cover">` : `<svg class="w-5 h-5 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`}
                <input type="hidden" name="productos[${idx}][imagen]" value="${product.imagen || ''}">
            </div>

            <div class="flex-1 space-y-1 min-w-0">
                <input type="text" name="productos[${idx}][nombre]" value="${product.nombre}" class="w-full font-bold text-gray-800 outline-none focus:text-blue-600 bg-transparent placeholder:text-gray-400 text-sm" placeholder="Nombre producto">
                <input type="text" name="productos[${idx}][descripcion]" value="${product.descripcion || ''}" class="w-full text-xs text-gray-400 outline-none bg-transparent placeholder:text-gray-300 truncate focus:text-gray-600 focus:bg-gray-50 focus:p-1 focus:rounded transition-all" placeholder="Descripción corta">
                <input type="hidden" name="productos[${idx}][id]" value="${product.id || ''}">
            </div>
            
            <div class="flex flex-col items-end gap-1 shrink-0">
                 <!-- Qt & Price -->
                 <div class="flex items-center gap-1">
                     <input type="number" name="productos[${idx}][cantidad]" value="1" min="1" class="w-12 text-center text-sm font-bold bg-gray-50 border border-gray-200 rounded px-1 outline-none focus:ring-1 focus:ring-blue-200" oninput="recalcRow(${idx})">
                     <input type="text" name="productos[${idx}][unidad]" value="${product.unidad_nombre || 'un'}" class="w-10 text-center text-[10px] font-bold bg-white border border-gray-200 rounded px-1 outline-none uppercase">
                     <input type="number" name="productos[${idx}][precio]" value="${product.precio_base}" class="w-20 text-right text-sm font-bold bg-gray-50 border border-gray-200 rounded px-1 outline-none focus:ring-1 focus:ring-blue-200" oninput="recalcRow(${idx})">
                 </div>
                 
                 <!-- Total -->
                 <div class="flex items-center gap-2">
                    <select name="productos[${idx}][impuesto_p]" class="text-[9px] font-bold bg-white text-gray-400 border-none outline-none appearance-none text-right" onchange="recalcRow(${idx})">
                        ${taxOptions}
                    </select>
                    <span class="text-sm font-black text-indigo-600 row-total">$0.00</span>
                 </div>
            </div>
            
            <button type="button" onclick="removeItem(${idx})" class="absolute -top-2 -right-2 bg-white shadow-sm border border-gray-100 rounded-full p-1 text-gray-300 hover:text-red-500 hover:border-red-200 transition-all opacity-0 group-hover:opacity-100 z-10">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <input type="hidden" name="productos[${idx}][subtotal]" class="row-subtotal-input">
        </div>
        `;
        
        container.insertAdjacentHTML('beforeend', itemHtml);
        recalcRow(idx);
    }

    function removeItem(idx) {
        document.getElementById(`item-row-${idx}`).remove();
        recalcTotal();
    }
    
    // Helper for number parsing (handles 1.000,00 or 1,000.00 or 1000)
    function parseNumber(val) {
        if (!val) return 0;
        if (typeof val === 'number') return val;
        val = val.toString().trim();
        
        // Check if using comma as decimal (e.g. 1.200,50 or 84,00)
        // If it has comma and dot, we assume the last one is decimal
        // If it has only comma, treat as decimal
        
        if (val.indexOf(',') > -1 && val.indexOf('.') > -1) {
            if (val.lastIndexOf(',') > val.lastIndexOf('.')) {
                // 1.200,50 -> Remove dots, replace comma with dot
                val = val.replace(/\./g, '').replace(',', '.');
            } else {
                // 1,200.50 -> Remove commas
                val = val.replace(/,/g, '');
            }
        } else if (val.indexOf(',') > -1) {
             // 84,00 or 1,000 (ambiguous, but usually decimal in this context if 2 decimals)
             // We'll replace comma with dot to be safe for JS
             val = val.replace(',', '.');
        }
        
        return parseFloat(val) || 0;
    }

    function recalcRow(idx) {
        const row = document.getElementById(`item-row-${idx}`);
        if(!row) return;

        const qtyInput = row.querySelector(`input[name="productos[${idx}][cantidad]"]`);
        const priceInput = row.querySelector(`input[name="productos[${idx}][precio]"]`);
        
        const qty = parseNumber(qtyInput.value);
        const price = parseNumber(priceInput.value);
        
        let rowSubtotal = qty * price;
        
        // Calculate Tax
        const taxSelect = row.querySelector(`select[name="productos[${idx}][impuesto_p]"]`);
        const taxP = taxSelect ? parseFloat(taxSelect.value) : 0;
        const rowTaxAmount = rowSubtotal * (taxP / 100);
        
        const rowTotal = rowSubtotal + rowTaxAmount;
        
        // Update UI
        row.querySelector('.row-total').textContent = '$' + rowTotal.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        row.querySelector('.row-subtotal-input').value = rowSubtotal.toFixed(2); 
        
        recalcTotal();
    }
    
    let sumAllSections = false;
    function toggleSumAll(cb) {
        sumAllSections = cb.checked;
        recalcTotal();
    }
    function recalcTotal() {
        let firstSectionTotal = 0;
        let firstSectionSub = 0;
        let firstSectionImp = 0;
        
        let globalTotal = 0;
        let globalSub = 0;
        let globalImp = 0;
        
        let hasSections = false;
        
        // Iterate sections
        document.querySelectorAll('.section-block').forEach((sec, index) => {
            let secSub = 0;
            let secImp = 0;
            const secName = sec.querySelector('input').value; 
            
            sec.querySelectorAll('.item-row').forEach(row => {
                 const idx = row.id.replace('item-row-', '');
                 const subInput = row.querySelector('.row-subtotal-input');
                 const val = parseFloat(subInput.value) || 0; // Already calculated in recalcRow which uses parseNumber
                 
                 const taxSelect = row.querySelector(`select[name="productos[${idx}][impuesto_p]"]`);
                 const taxP = taxSelect ? parseFloat(taxSelect.value) : 0;
                 
                 // Update section name calc
                 const nameInp = row.querySelector(`input[name="productos[${idx}][seccion]"]`);
                 if(nameInp) nameInp.value = secName;

                 secSub += val;
                 secImp += val * (taxP / 100);
            });
            
            const secTotal = secSub + secImp;
            const secTotalFmt = '$' + secTotal.toLocaleString('es-CO', {minimumFractionDigits: 2});

            // Update Section Header Total
            const secTotEl = sec.querySelector('.section-total');
            if(secTotEl) secTotEl.textContent = secTotalFmt;
            
            // Update Section Footer Total
            const secFootTotEl = sec.querySelector('.section-footer-total');
            if(secFootTotEl) secFootTotEl.textContent = secTotalFmt;

            // Accumulate Global
            globalSub += secSub;
            globalImp += secImp;
            globalTotal += secTotal;

            // First Section Specifics
            if (index === 0) {
                firstSectionSub = secSub;
                firstSectionImp = secImp;
                firstSectionTotal = secTotal;
                hasSections = true;
            }
        });

        // ---------------------------------------------------------
        // VISIBILITY LOGIC
        // ---------------------------------------------------------
        // If "Sum All" is unchecked: Hide Global Footer & Header Total
        // If "Sum All" is checked: Show Global Footer & Header Total
        // ---------------------------------------------------------
        
        // Let's rely on ID `mini-total`'s parent container.
        const miniTotalEl = document.getElementById('mini-total');
        if (miniTotalEl) {
             const footerContainer = miniTotalEl.closest('.p-8'); // Assuming this is the correct parent
             if(footerContainer) {
                 if(sumAllSections) {
                     footerContainer.classList.remove('hidden');
                 } else {
                     footerContainer.classList.add('hidden');
                 }
             }
        }
        
        // Header Total Logic
        const headTot = document.getElementById('header-total');
        if(headTot) {
             const headContainer = headTot.parentElement; // The div containing label and total
             if(headContainer) { // Ensure container exists
                 if(sumAllSections) {
                     headContainer.classList.remove('opacity-0'); // or display none
                     headContainer.style.visibility = 'visible';
                 } else {
                     headContainer.classList.add('opacity-0');
                     headContainer.style.visibility = 'hidden';
                 }
             }
        }
        
        // Mobile Total Logic
        const mobTot = document.getElementById('mobile-total');
        if(mobTot) {
            mobTot.style.display = sumAllSections ? 'block' : 'none';
        }

        // Decide what to show (First Option Only vs All)
        let displaySub = sumAllSections ? globalSub : firstSectionSub;
        let displayImp = sumAllSections ? globalImp : firstSectionImp;
        let displayTotal = sumAllSections ? globalTotal : firstSectionTotal;

        if (hasSections) {
            const elSub = document.getElementById('mini-subtotal');
            const elImp = document.getElementById('mini-impuestos');
            const elTotal = document.getElementById('mini-total');

            if(elSub) elSub.textContent = '$' + displaySub.toLocaleString('es-CO', {minimumFractionDigits: 2});
            if(elImp) elImp.textContent = '$' + displayImp.toLocaleString('es-CO', {minimumFractionDigits: 2});
            
            const totalFmt = '$' + displayTotal.toLocaleString('es-CO', {minimumFractionDigits: 2});
            if(elTotal) elTotal.textContent = totalFmt;
            
            if(headTot) headTot.textContent = totalFmt;
            if(mobTot) mobTot.textContent = totalFmt;

            // Label update
            if(headTot && headTot.previousElementSibling) {
                headTot.previousElementSibling.textContent = sumAllSections ? "TOTAL FINAL (SUMA TODO)" : "TOTAL (OPCIÓN PRINCIPAL)";
            }

            // Hidden Inputs for Submit
            const inSub = document.getElementById('input-subtotal');
            const inImp = document.getElementById('input-impuestos');
            const inTot = document.getElementById('input-total');

            if(inSub) inSub.value = displaySub.toFixed(2);
            if(inImp) inImp.value = displayImp.toFixed(2);
            if(inTot) inTot.value = displayTotal.toFixed(2);
        } else {
            const elTotal = document.getElementById('mini-total');
            if(elTotal) elTotal.textContent = '$0.00';
            const inTot = document.getElementById('input-total');
            if(inTot) inTot.value = 0;

            if (elTotal) {
                const footerContainer = elTotal.closest('.p-8');
                if(footerContainer) footerContainer.classList.add('hidden');
            }
            const headTot = document.getElementById('header-total');
            if(headTot) {
                const headContainer = headTot.parentElement;
                if(headContainer) {
                    headContainer.classList.add('opacity-0');
                    headContainer.style.visibility = 'hidden';
                }
            }
            const mobTot = document.getElementById('mobile-total');
            if(mobTot) mobTot.style.display = 'none';
        }
        
        triggerAutoSave();
    }

    // Auto-Save Logic
    let lastSavedData = '';
    let cotizacionID = <?php echo isset($_GET['id']) ? (int)$_GET['id'] : 'null'; ?>;

    function triggerAutoSave() {
        const form = document.getElementById('wizardForm');
        const formData = new FormData(form);
        if (cotizacionID) formData.append('id', cotizacionID);

        // DEBUG: Check what is being sent
        console.log("AutoSaving... Form ID:", formData.get('formulario_id'), "Mode:", formData.get('formulario_modo'));

        fetch('api_autosave.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                cotizacionID = data.id;
                // Update Number in UI if provided (first save)
                if (data.numero) {
                    const numInput = document.querySelector('input[name="numero_cotizacion"]');
                    if(numInput) numInput.value = data.numero;
                    
                    // Also update any display text if exists (usually headers)
                    // Assuming there might be a header showing the number
                }
                // Silent success or optional indicator
                // console.log("Auto-saved draft", data.id);
            }
        })
        .catch(err => console.error("Auto-save error", err));
    }

    // Debounce or Interval for heavy edits
    setInterval(() => {
        const currentData = new FormData(document.getElementById('wizardForm'));
        let serialized = '';
        for (let pair of currentData.entries()) serialized += pair[0] + pair[1];
        
        if (serialized !== lastSavedData) {
            lastSavedData = serialized;
            triggerAutoSave();
        }
    }, 30000); // Check every 30s

    function switchPreviewMode(mode) {
        document.getElementById('view-editor').classList.toggle('hidden', mode !== 'editor');
        document.getElementById('view-preview').classList.toggle('hidden', mode !== 'preview');
        
        const btnEd = document.getElementById('btn-mode-editor');
        const btnPr = document.getElementById('btn-mode-preview');
        const toolbar = document.getElementById('toolbar-editor');

        if(mode === 'editor') {
            btnEd.classList.replace('text-gray-500', 'text-blue-600');
            btnEd.classList.add('bg-white', 'shadow-sm');
            btnPr.classList.replace('text-blue-600', 'text-gray-500');
            btnPr.classList.remove('bg-white', 'shadow-sm');
            toolbar.classList.remove('hidden');
        } else {
            // Switch to Preview: Auto-save editor content first
            saveDraftContent().then(() => {
                loadPreviewFrame();
            });

            btnPr.classList.replace('text-gray-500', 'text-blue-600');
            btnPr.classList.add('bg-white', 'shadow-sm');
            btnEd.classList.replace('text-blue-600', 'text-gray-500');
            btnEd.classList.remove('bg-white', 'shadow-sm');
            toolbar.classList.add('hidden');
        }
    }

    function insertarTag(tag) {
        const textarea = document.getElementById('editor-html');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after  = text.substring(end, text.length);
        textarea.value = (before + tag + after);
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        textarea.focus();
    }

    let currentHash = '';

    async function loadPreviewStep() {
        const form = document.getElementById('wizardForm');
        const formData = new FormData(form);
        if (cotizacionID) formData.append('id', cotizacionID);

        try {
            // 1. Save Data
            const res = await fetch('api_autosave.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.status === 'success') {
                cotizacionID = data.id;
                currentHash = data.hash;

                // 2. Fetch HTML Content
                const resHtml = await fetch(`api_design_handler.php?id=${cotizacionID}`); 
                const dataHtml = await resHtml.json();
                
                if(dataHtml.status === 'success') {
                     // If editor is empty or we just loaded, fill it. 
                     // Only overwrite if editor is empty to avoid losing unsaved changes if this func calls repeatedly
                     // BUT, for Step Switch, we expect it to load saved.
                     const editorVal = document.getElementById('editor-html').value;
                     if (!editorVal || editorVal.trim() === '') {
                        document.getElementById('editor-html').value = dataHtml.html || ''; 
                     }
                }

                // Default view
                switchPreviewMode('preview');
                
            } else {
                alert("Error: " + data.message);
            }
        } catch(e) {
            console.error(e);
        }
    }

    async function saveDraftContent() {
        const html = document.getElementById('editor-html').value;
        const btn = document.getElementById('btn-save-draft');
        if(btn) btn.innerHTML = '...';
        
        const fd = new FormData();
        fd.append('id', cotizacionID);
        fd.append('contenido_html', html);
        
        try {
            await fetch('api_design_handler.php', { method: 'POST', body: fd });
            if(btn) btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Guardado';
            setTimeout(() => { if(btn) btn.innerHTML = '<span class="hidden md:inline">Guardar</span>'; }, 2000);
        } catch(e) {
            alert('Error guardando diseño');
        }
    }

    function loadPreviewFrame() {
        const frame = document.getElementById('preview-frame');
        if(currentHash) {
             frame.src = `propuesta.php?h=${currentHash}&preview=1&t=${Date.now()}`;
        }
    }


    async function confirmAndSend() {
        const clientInput = document.getElementById('input-cliente-id');
        if(!clientInput || !clientInput.value) {
            alert("Por favor selecciona un cliente antes de enviar.");
            goToStep(1);
            return;
        }

        // Fill Modal Info
        const companyName = document.getElementById('selected-client-name').textContent;
        const contactDetail = document.getElementById('selected-client-detail').textContent;
        const clientCard = document.getElementById('selected-client-card');
        
        const clientEmail = clientCard.dataset.email || "";
        const clientWA = clientCard.dataset.wa || "";
        
        document.getElementById('send-client-company').textContent = companyName;
        document.getElementById('send-avatar').textContent = companyName.charAt(0);
        document.getElementById('send-client-contact').textContent = "Contacto: " + contactDetail;

        const emailInput = document.getElementById('primary-email');
        const waInput = document.getElementById('primary-wa');
        
        // Populate Both
        emailInput.value = clientEmail;
        waInput.value = clientWA.replace(/\D/g, '');

        document.getElementById('modalSendQuote').classList.remove('hidden');
    }

    function cerrarModalSend() {
        document.getElementById('modalSendQuote').classList.add('hidden');
        // Clean dynamic rows
        document.querySelectorAll('.dynamic-recipient').forEach(el => el.remove());
    }

    function addRecipientRow(type) {
        const container = document.getElementById(type + '-recipients-list');
        const div = document.createElement('div');
        div.className = 'flex gap-2 group dynamic-recipient animate-fade-in-down';
        
        if (type === 'email') {
            div.innerHTML = `
                <div class="relative flex-1">
                    <input type="email" name="send_emails[]" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-100 bg-white outline-none focus:ring-2 focus:ring-blue-500 transition-all text-sm font-bold" placeholder="correo@ejemplo.com">
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-blue-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="p-3 text-gray-300 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
        } else {
            div.innerHTML = `
                <div class="w-24 md:w-32">
                    <select name="send_wa_countries[]" class="w-full px-3 py-3 rounded-xl border border-gray-100 bg-white text-xs font-bold outline-none appearance-none">
                         <option value="57">🇨🇴 +57</option>
                         <option value="1">🇺🇸 +1</option>
                         <option value="34">🇪🇸 +34</option>
                         <option value="52">🇲🇽 +52</option>
                         <option value="54">🇦🇷 +54</option>
                         <option value="56">🇨🇱 +56</option>
                         <option value="51">🇵🇪 +51</option>
                         <option value="593">🇪🇨 +593</option>
                         <option value="507">🇵🇦 +507</option>
                    </select>
                </div>
                <div class="relative flex-1">
                    <input type="text" name="send_wa_numbers[]" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-100 bg-white outline-none focus:ring-2 focus:ring-green-500 transition-all text-sm font-bold" placeholder="3001234567">
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-green-500">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.27 9.27 0 01-4.487-1.164l-.322-.19-3.338.875.89-3.251-.208-.332A9.28 9.28 0 012.225 9.37C2.228 4.225 6.42 0 11.57 0a11.5 11.5 0 018.175 3.385 11.455 11.455 0 013.387 8.19c-.002 5.143-4.194 9.366-9.345 9.366z"/></svg>
                    </div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="p-3 text-gray-300 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
        }
        container.appendChild(div);
    }

    async function executeSending() {
        const btn = document.getElementById('btn-final-send');
        const originalText = btn.innerHTML;
        
        // Gather Emails
        const emails = [];
        document.querySelectorAll('input[name="send_emails[]"]').forEach(i => { if(i.value) emails.push(i.value); });
        
        // Gather WhatsApps
        const whatsapps = [];
        const waNumbers = document.querySelectorAll('input[name="send_wa_numbers[]"]');
        const waCountries = document.querySelectorAll('select[name="send_wa_countries[]"]');
        waNumbers.forEach((n, idx) => {
            if(n.value) {
                whatsapps.push({
                    number: n.value,
                    country: waCountries[idx].value
                });
            }
        });

        if (emails.length === 0 && whatsapps.length === 0) {
            alert("Por favor ingresa al menos un destinatario.");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24">...</svg> Enviando...';

        try {
            const res = await fetch('api_send_quote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: cotizacionID,
                    emails: emails,
                    whatsapps: whatsapps,
                    message: document.getElementById('send-custom-message').value
                })
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                alert("✅ Éxito: " + data.message);
                window.location.href = 'cotizaciones.php';
            } else {
                alert("❌ Error: " + data.message);
            }
        } catch (e) {
            alert("Error de conexión con el servidor");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    // Listas de clientes/prospectos para el selector (Legacy, used for initial load if needed, but we use AJAX now)
    // const clientesList = ...; 

    // Init Search Logic
    // Unified Smart Search Logic
    window.currentClientType = '<?php echo $label_type; ?>'; // Load from PHP
    let clientSearchTimeout;

    // Initialize Toggle UI on Load
    document.addEventListener('DOMContentLoaded', () => {
        setSearchType(window.currentClientType);
    });

    window.setSearchType = function(type) {
        window.currentClientType = type;
        
        // Update Toggles
        document.querySelectorAll('.type-toggle-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.getElementById('toggle-' + type);
        if(activeBtn) activeBtn.classList.add('active');
        
        // Refresh search if content exists
        const input = document.getElementById('smart-search-input');
        if(input && input.value.length >= 2) handleSmartSearch(input.value);
    };

    window.handleSmartSearch = function(query) {
        clearTimeout(clientSearchTimeout);
        const resultsContainer = document.getElementById('smart-search-results');
        const resultsList = document.getElementById('results-list');

        if(query.length < 2) {
            resultsContainer.classList.remove('active');
            return;
        }

        clientSearchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`api_search_clients.php?q=${encodeURIComponent(query)}&type=${window.currentClientType || 'cliente'}`);
                const data = await res.json();
                
                if (!resultsList || !resultsContainer) return;

                resultsList.innerHTML = '';
                resultsContainer.classList.add('active');

                if (data.status === 'success' && data.data && data.data.length > 0) {
                    data.data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'p-3 flex items-center gap-3 cursor-pointer hover:bg-blue-50 rounded-xl transition-all group';
                        div.onclick = () => selectClient(item.id, item.nombre, item.identificacion || item.email, item.email, item.celular_contacto || item.telefono || '');
                        div.innerHTML = `
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 font-black group-hover:bg-blue-600 group-hover:text-white transition-all">
                                ${item.nombre ? item.nombre.charAt(0) : '?'}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-gray-800 truncate">${item.nombre}</p>
                                <p class="text-[10px] text-gray-400 font-medium truncate">${item.email || item.identificacion || 'Sin ID'}</p>
                            </div>
                        `;
                        resultsList.appendChild(div);
                    });
                } else {
                    resultsList.innerHTML = `
                        <div class="p-6 text-center">
                            <p class="text-xs text-gray-400 font-bold">No se encontraron resultados</p>
                        </div>
                    `;
                }
            } catch (err) {
                console.error("Search error:", err);
            }
        }, 300);
    };

    window.selectClient = function(id, name, detail, email, wa) {
        document.getElementById('input-cliente-id').value = id;
        document.getElementById('selected-client-name').textContent = name;
        document.getElementById('selected-client-detail').textContent = detail || email || '';
        document.getElementById('selected-client-avatar').textContent = name.charAt(0);
        document.getElementById('selected-client-badge').textContent = window.currentClientType;
        
        // Hidden Attributes for sending
        const card = document.getElementById('selected-client-card');
        card.dataset.email = email || '';
        card.dataset.wa = wa || '';

        // UI Transitions
        document.getElementById('empty-client-card').classList.add('hidden');
        document.getElementById('selected-client-card').classList.remove('hidden');
        document.getElementById('selected-client-card').classList.add('flex');
        
        // Close search results
        document.getElementById('smart-search-results').classList.remove('active');
        document.getElementById('smart-search-input').value = '';
    };

    window.clearClientSelection = function() {
        document.getElementById('input-cliente-id').value = '';
        document.getElementById('selected-client-card').classList.add('hidden');
        document.getElementById('selected-client-card').classList.remove('flex');
        document.getElementById('empty-client-card').classList.remove('hidden');
        
        // Reset Search Input Focus
        setTimeout(() => document.getElementById('smart-search-input').focus(), 100);
    };

    console.log("🚀 Smart Search Redesign Loaded");
</script>

<?php include 'includes/footer.php'; ?>
