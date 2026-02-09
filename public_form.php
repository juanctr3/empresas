<?php
require_once 'db.php';
$hash = $_GET['hash'] ?? '';

if (!$hash) {
    die("Formulario no encontrado.");
}

// Fetch Form
$stmt = $pdo->prepare("SELECT * FROM formularios WHERE hash_publico = ? AND is_active = 1");
$stmt->execute([$hash]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Formulario no encontrado o inactivo.");
}

// Fetch Fields
$stmtF = $pdo->prepare("SELECT * FROM formulario_campos WHERE formulario_id = ? ORDER BY order_index ASC");
$stmtF->execute([$form['id']]);
$fields = $stmtF->fetchAll(PDO::FETCH_ASSOC);

// Parse JSON fields
foreach($fields as &$f) {
    $f['options'] = json_decode($f['options'] ?? '[]', true);
    $f['validation'] = json_decode($f['validation'] ?? '{}', true);
    $f['settings'] = json_decode($f['settings'] ?? '{}', true);
    $f['visibility_rules'] = json_decode($f['visibility_rules'] ?? 'null', true);
}
unset($f);

// Group fields by steps
$steps = [];
$currentStepIdx = 0;
$hasStarted = false;

foreach($fields as $f) {
    if ($f['type'] === 'step') {
        if (!$hasStarted) {
            // First item is a step, use it as the first step
            $steps[$currentStepIdx] = ['label' => $f['label'], 'fields' => []];
            $hasStarted = true;
        } else {
            // Subsequent steps
            $currentStepIdx++;
            $steps[$currentStepIdx] = ['label' => $f['label'], 'fields' => []];
        }
        continue;
    }
    
    if (!$hasStarted) {
        // First item is NOT a step, create default Paso 1
        $steps[$currentStepIdx] = ['label' => 'Paso 1', 'fields' => []];
        $hasStarted = true;
    }
    
    $steps[$currentStepIdx]['fields'][] = $f;
}

// Ensure at least one step if there are fields but no steps were defined
if (empty($steps) && !empty($fields)) {
    $steps[0] = ['label' => 'Formulario', 'fields' => $fields];
}

// Fetch Company config for Google Maps
$stmtE = $pdo->prepare("SELECT google_maps_api_key FROM empresas WHERE id = ?");
$stmtE->execute([$form['empresa_id']]);
$empresaConfig = $stmtE->fetch(PDO::FETCH_ASSOC);
$mapsKey = $empresaConfig['google_maps_api_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['titulo']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    <?php if($mapsKey): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $mapsKey; ?>&libraries=places"></script>
    <?php endif; ?>
    <style>
        body { 
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        
        input:focus, select:focus, textarea:focus {
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        /* Animations */
        .step-transition {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="p-4 md:p-10 flex items-start justify-center">

    <div class="w-full max-w-4xl">
        <!-- Main Card -->
        <div class="glass rounded-[2.5rem] shadow-2xl overflow-hidden fade-in">
            
            <!-- Progress Bar (Only if multiple steps) -->
            <?php if(count($steps) > 1): ?>
            <div class="bg-white/30 h-1.5 w-full flex">
                <?php for($i=0; $i<count($steps); $i++): ?>
                    <div id="progress-bar-<?php echo $i; ?>" class="h-full transition-all duration-700 bg-indigo-500/20" style="flex: 1"></div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <div class="p-8 md:p-12">
                <!-- Header -->
                <?php 
                $config = json_decode($form['config'] ?? '{}', true);
                $showHeader = $config['show_header'] !== false;
                $heroImage = $config['hero_image'] ?? '';
                ?>

                <?php if($showHeader): ?>
                <header class="text-center mb-10">
                    <div class="w-20 h-20 bg-white shadow-xl shadow-indigo-100/50 rounded-3xl mx-auto mb-6 flex items-center justify-center text-indigo-500 transform rotate-3 overflow-hidden">
                        <?php if($heroImage): ?>
                            <img src="<?php echo htmlspecialchars($heroImage); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight leading-tight mb-2"><?php echo htmlspecialchars($form['titulo']); ?></h1>
                    <p class="text-gray-500 font-medium"><?php echo htmlspecialchars($form['descripcion']); ?></p>
                </header>
                <?php endif; ?>

                <form id="public-form" class="space-y-8">
            <?php
            $sIdx = 0;
            
            // Fetch Quote Data if linked
            $quoteData = [];
            $cot_id = $_GET['cotizacion_id'] ?? null;
            if($cot_id) {
                try {
                    $stmtQ = $pdo->prepare("SELECT c.id, c.total_cotizacion, cl.nombre_empresa, cl.nombre_contacto, cl.email, cl.telefono 
                                           FROM cotizaciones c 
                                           LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                                           WHERE c.id = ?");
                    $stmtQ->execute([$cot_id]);
                    $quoteData = $stmtQ->fetch(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $eQuote) {
                    // Fail silently or log error
                }
            }
            
            foreach($steps as $step): 
            ?>
                <!-- Step <?php echo $sIdx + 1; ?> -->
                <div id="step-<?php echo $sIdx; ?>" class="step-content <?php echo $sIdx === 0 ? '' : 'hidden'; ?> animate-in slide-in-from-right-4 duration-500">
                    <h2 class="text-2xl font-black text-gray-800 mb-6 uppercase tracking-tight"><?php echo htmlspecialchars($step['label']); ?></h2>
                    
                    <div class="flex flex-wrap -mx-2">
                    <?php 
                    foreach($step['fields'] as $field): 
                        $width = $field['settings']['width'] ?? '100';
                        $isHidden = $field['settings']['is_hidden'] ?? false;
                        
                        // Handle Hidden Fields
                        if ($isHidden) {
                            echo '<input type="hidden" name="'.$field['name'].'" value="'.htmlspecialchars($field['placeholder']).'">'; 
                            continue; 
                        }

                        $widthClass = 'w-full';
                        if($width == '50') $widthClass = 'w-full md:w-1/2';
                        else if($width == '33') $widthClass = 'w-full md:w-1/3';
                        else if($width == '25') $widthClass = 'w-full md:w-1/4';
                        else if($width == '66') $widthClass = 'w-full md:w-2/3';
                        else if($width == '75') $widthClass = 'w-full md:w-3/4';
                    ?>
                        <div class="px-2 mb-6 <?php echo $widthClass; ?> field-container transition-all duration-300" data-field-id="<?php echo $field['id']; ?>">
                                    <label class="flex items-center gap-3 text-xs font-black text-gray-500 uppercase tracking-widest mb-3 group-focus-within:text-indigo-600 transition-colors">
                                        <span class="w-6 h-6 flex items-center justify-center bg-gray-100 rounded-lg text-gray-400 group-focus-within:bg-indigo-600 group-focus-within:text-white transition-all overflow-hidden shrink-0">
                                            <?php 
                                            $iconType = $field['settings']['icon_type'] ?? 'icon';
                                            if($iconType === 'image' && $field['icon']): ?>
                                                <img src="<?php echo htmlspecialchars($field['icon']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?php echo getIconSvgByName($field['icon'] ?: 'cube'); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($field['label']); ?>
                                        <?php if($field['validation']['required'] ?? false): ?>
                                            <span class="text-red-500">*</span>
                                        <?php endif; ?>
                                    </label>

                                    <?php if($field['type'] === 'text' || $field['type'] === 'email' || $field['type'] === 'number' || $field['type'] === 'date' || $field['type'] === 'time' || $field['type'] === 'url' || $field['type'] === 'document'): ?>
                                        <input type="<?php echo (in_array($field['type'], ['full_name', 'text', 'document'])) ? 'text' : $field['type']; ?>" 
                                            name="<?php echo $field['name']; ?>"
                                            placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>"
                                            <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?>
                                            class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                                    
                                    <?php elseif($field['type'] === 'full_name'): ?>
                                        <div class="flex flex-col sm:flex-row gap-4">
                                            <input type="text" name="<?php echo $field['name']; ?>_name" placeholder="Nombres" <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?> class="flex-1 bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                                            <input type="text" name="<?php echo $field['name']; ?>_surname" placeholder="Apellidos" <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?> class="flex-1 bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                                            <input type="hidden" name="<?php echo $field['name']; ?>" id="input-full-name-<?php echo $field['id']; ?>" data-name="<?php echo $field['name']; ?>">
                                        </div>
                                    
                                    <?php elseif($field['type'] === 'textarea'): ?>
                                        <textarea name="<?php echo $field['name']; ?>" rows="4" 
                                            placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>"
                                            <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?>
                                            class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm resize-none"></textarea>

                                    <?php elseif($field['type'] === 'select'): ?>
                                        <select name="<?php echo $field['name']; ?>" 
                                            <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?>
                                            class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm appearance-none">
                                            <option value=""><?php echo htmlspecialchars($field['placeholder'] ?: 'Selecciona una opción'); ?></option>
                                            <?php foreach($field['options'] as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif($field['type'] === 'radio' || $field['type'] === 'checkbox'): ?>
                                        <div class="space-y-3">
                                            <?php foreach($field['options'] as $optIdx => $opt): ?>
                                                <label class="flex items-center gap-3 p-4 bg-white/50 border border-gray-100 rounded-2xl cursor-pointer hover:bg-white transition-all">
                                                    <input type="<?php echo $field['type']; ?>" name="<?php echo $field['name']; ?><?php echo $field['type'] === 'checkbox' ? '[]' : ''; ?>" value="<?php echo htmlspecialchars($opt); ?>" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                                                    <span class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif($field['type'] === 'phone'): ?>
                                        <div class="flex gap-2">
                                            <select name="<?php echo $field['name']; ?>_prefix" class="w-24 bg-white/50 border border-gray-200 rounded-2xl px-3 py-4 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                                <option value="+57">🇨🇴 +57</option>
                                                <option value="+52">🇲🇽 +52</option>
                                                <option value="+1">🇺🇸 +1</option>
                                                <option value="+34">🇪🇸 +34</option>
                                            </select>
                                            <input type="tel" name="<?php echo $field['name']; ?>"
                                                placeholder="Número de celular"
                                                class="flex-1 bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                        </div>

                                    <?php elseif($field['type'] === 'address'): ?>
                                        <div class="space-y-3">
                                            <div class="relative">
                                                <input type="text" name="<?php echo $field['name']; ?>" id="address-<?php echo $field['id']; ?>"
                                                    placeholder="Escribe tu dirección..."
                                                    class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all pr-12">
                                                <button type="button" onclick="getLocation('address-<?php echo $field['id']; ?>')" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                </button>
                                            </div>
                                        </div>

                                    <?php elseif($field['type'] === 'signature'): ?>
                                        <div class="bg-white/50 border border-gray-200 rounded-3xl p-2 shadow-inner">
                                            <canvas id="signature-<?php echo $field['id']; ?>" class="w-full h-40 rounded-2xl cursor-crosshair bg-white" style="touch-action: none;"></canvas>
                                            <button type="button" onclick="clearSignature('signature-<?php echo $field['id']; ?>')" class="mt-2 text-[10px] font-black uppercase text-gray-400 hover:text-red-500 px-3 py-1 transition-colors">Limpiar Firma</button>
                                            <input type="hidden" name="<?php echo $field['name']; ?>" id="input-signature-<?php echo $field['id']; ?>">
                                        </div>

                                     <?php elseif($field['type'] === 'time_range'): ?>
                                        <div class="flex items-center gap-3">
                                            <div class="flex-1">
                                                <label class="text-[10px] uppercase font-black text-gray-400 mb-1 block">Desde</label>
                                                <input type="time" name="<?php echo $field['name']; ?>_from" 
                                                    id="time-from-<?php echo $field['id']; ?>"
                                                    onchange="updateTimeRange(<?php echo $field['id']; ?>, '<?php echo $field['name']; ?>')"
                                                    <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?>
                                                    class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                                            </div>
                                            <div class="flex-1">
                                                <label class="text-[10px] uppercase font-black text-gray-400 mb-1 block">Hasta</label>
                                                <input type="time" name="<?php echo $field['name']; ?>_to" 
                                                    id="time-to-<?php echo $field['id']; ?>"
                                                    onchange="updateTimeRange(<?php echo $field['id']; ?>, '<?php echo $field['name']; ?>')"
                                                    <?php echo ($field['validation']['required'] ?? false) ? 'required' : ''; ?>
                                                    class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                                            </div>
                                            <input type="hidden" name="<?php echo $field['name']; ?>" id="input-time-range-<?php echo $field['id']; ?>" data-name="<?php echo $field['name']; ?>">
                                        </div>

                                    <?php elseif($field['type'] === 'product'): ?>
                                        <div class="space-y-4">
                                            <div class="relative">
                                                <input type="text" placeholder="Buscar producto..." oninput="searchProducts(this, 'results-<?php echo $field['id']; ?>')" class="w-full bg-white/50 border border-gray-200 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                                <div id="results-<?php echo $field['id']; ?>" class="absolute z-10 w-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden p-2 space-y-1 overflow-hidden"></div>
                                            </div>
                                            <div id="selected-products-<?php echo $field['id']; ?>" class="flex flex-wrap gap-2 text-indigo-500 font-bold"></div>
                                            <input type="hidden" name="<?php echo $field['name']; ?>" id="input-product-<?php echo $field['id']; ?>">
                                        </div>

                                    <?php elseif($field['type'] === 'title'): ?>
                                        <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight"><?php echo htmlspecialchars($field['label']); ?></h2>
                                        <div class="h-1 w-10 bg-indigo-500 rounded-full"></div>

                                    <?php elseif($field['type'] === 'image'): ?>
                                        <div class="rounded-3xl overflow-hidden shadow-lg border border-gray-100 bg-gray-50 aspect-video flex items-center justify-center">
                                            <img src="<?php echo htmlspecialchars($field['placeholder'] ?: 'https://via.placeholder.com/600x400?text=Imagen+Informativa'); ?>" class="w-full h-full object-cover">
                                        </div>

                                    <?php elseif($field['type'] === 'html'): ?>
                                        <div class="prose prose-sm max-w-none text-gray-600">
                                            <?php echo $field['placeholder']; ?>
                                        </div>

                                    <?php elseif($field['type'] === 'captcha'): ?>
                                        <div class="flex items-center gap-4 p-5 bg-indigo-50 rounded-2xl border border-indigo-100">
                                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center font-black text-indigo-600 shadow-sm">
                                                <span id="captcha-text-<?php echo $field['id']; ?>">? + ?</span>
                                            </div>
                                            <input type="number" name="captcha_<?php echo $field['id']; ?>" required placeholder="Resultado" class="flex-1 bg-white border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">
                                        </div>

                                        <label class="flex items-start gap-4 p-5 bg-white/50 border border-gray-100 rounded-3xl cursor-pointer hover:bg-white transition-all group">
                                            <input type="checkbox" name="<?php echo $field['name']; ?>" required class="mt-1 w-6 h-6 text-indigo-600 rounded-lg focus:ring-indigo-500 border-gray-300">
                                            <span class="text-sm font-bold text-gray-700 leading-relaxed">
                                                <?php echo $field['settings']['consent_html'] ?: htmlspecialchars($field['placeholder'] ?: 'Acepto los términos y condiciones de tratamiento de datos personales.'); ?>
                                            </span>
                                        </label>

                                    <?php elseif($field['type'] === 'file'): ?>
                                        <div class="space-y-4">
                                            <div class="relative group">
                                                <input type="file" 
                                                    id="file-<?php echo $field['id']; ?>"
                                                    accept=".<?php echo str_replace(', ', ',.', $field['settings']['allowed_extensions'] ?? 'pdf,jpg,png,docx'); ?>"
                                                    onchange="handleFileUpload(this, '<?php echo $field['id']; ?>')"
                                                    class="hidden">
                                                <label for="file-<?php echo $field['id']; ?>" class="flex flex-col items-center justify-center w-full p-8 border-2 border-dashed border-gray-200 rounded-[2rem] bg-white/50 hover:bg-white hover:border-indigo-400 transition-all cursor-pointer">
                                                    <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 mb-4 group-hover:scale-110 transition-transform">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                                    </div>
                                                    <span class="text-sm font-black text-gray-900 uppercase tracking-widest">Seleccionar Archivo</span>
                                                    <span class="text-[10px] text-gray-400 font-bold mt-2 uppercase tracking-wide">Máx: <?php echo $field['settings']['max_size_mb'] ?? '5'; ?>MB (<?php echo $field['settings']['allowed_extensions'] ?? 'pdf, jpg, png, docx'; ?>)</span>
                                                </label>
                                            </div>
                                            <div id="file-list-<?php echo $field['id']; ?>" class="space-y-2"></div>
                                            <input type="hidden" name="<?php echo $field['name']; ?>" id="input-file-<?php echo $field['id']; ?>">
                                        </div>

                                    <?php elseif($field['type'] === 'smart_field'): 
                                        $source = $field['settings']['smart_source'] ?? 'quote_id';
                                        $val = '';
                                        if($source === 'quote_id') $val = $quoteData['id'] ?? '';
                                        if($source === 'client_name') $val = $quoteData['nombre_contacto'] ?? ($quoteData['nombre_empresa'] ?? '');
                                        if($source === 'client_email') $val = $quoteData['email'] ?? '';
                                        if($source === 'client_phone') $val = $quoteData['telefono'] ?? '';
                                        if($source === 'total_amount') $val = $quoteData['total_cotizacion'] ?? '';
                                        if($source === 'public_link') $val = $quoteData['hash_publico'] ?? ''; // Need to construct full link ideally
                                    ?>
                                        <div class="relative group">
                                            <input type="text" 
                                                name="<?php echo $field['name']; ?>" 
                                                id="smart-<?php echo $field['id']; ?>"
                                                value="<?php echo htmlspecialchars($val); ?>"
                                                readonly
                                                class="w-full bg-purple-50 border border-purple-100 rounded-xl px-4 py-4 pl-12 font-bold text-purple-700 outline-none cursor-not-allowed">

                                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-purple-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            </div>
                                            <label class="absolute -top-2.5 left-4 bg-purple-50 px-2 text-[10px] font-black uppercase tracking-widest text-purple-400">
                                                <?php echo htmlspecialchars($field['label']); ?>
                                            </label>
                                        </div>

                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            </div> <!-- End flex-wrap -->
                            
                            <!-- Step Actions -->
                            <div class="flex gap-4 pt-6">
                                <?php if($sIdx > 0): ?>
                                    <button type="button" onclick="changeStep(<?php echo $sIdx - 1; ?>)" class="flex-1 bg-gray-100 text-gray-700 font-black uppercase tracking-widest py-5 rounded-[1.5rem] hover:bg-gray-200 transition-all border border-gray-200 shadow-xl shadow-gray-200/50">Anterior</button>
                                <?php endif; ?>

                                <?php if($sIdx < count($steps) - 1): ?>
                                    <button type="button" onclick="changeStep(<?php echo $sIdx + 1; ?>)" class="flex-[2] bg-indigo-600 text-white font-black uppercase tracking-widest py-5 rounded-[1.5rem] hover:bg-indigo-700 transition-all shadow-2xl shadow-indigo-200 active:scale-95">Siguiente</button>
                                <?php else: ?>
                                    <button type="submit" class="flex-[2] bg-indigo-600 text-white font-black uppercase tracking-widest py-5 rounded-[1.5rem] hover:bg-indigo-700 transition-all shadow-2xl shadow-indigo-200 active:scale-95">
                                        <?php echo $step['submit_label'] ?? $form['submit_label'] ?? 'Enviar Formulario'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php $sIdx++; endforeach; ?>
                </form>

                <!-- Success Screen -->
                <div id="success-screen" class="hidden flex flex-col items-center justify-center py-20 text-center animate-in zoom-in duration-500">
                    <div class="w-24 h-24 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-10 shadow-xl shadow-green-100 border-8 border-white">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-3xl font-black text-gray-900 mb-4 uppercase tracking-tighter italic">¡Éxito Total!</h2>
                    <p class="text-gray-500 font-medium mb-10">Tus datos han sido enviados y procesados correctamente. <br>¡Gracias por tu tiempo!</p>
                    <button onclick="location.reload()" class="bg-gray-900 text-white font-black uppercase tracking-widest px-10 py-4 rounded-3xl hover:bg-black transition-all shadow-2xl shadow-gray-200">Cerrar</button>
                </div>

            </div>
        </div>
        
        <!-- Branding -->
        <div class="mt-10 flex flex-col items-center gap-1 opacity-40 hover:opacity-100 transition-all duration-700">
            <span class="text-[9px] font-black uppercase tracking-[0.3em] text-gray-500">Impulsado por</span>
            <span class="text-xs font-black text-gray-900 uppercase tracking-widest italic">CoticeFácil OS</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        let currentStep = 0;
        const totalSteps = <?php echo count($steps); ?>;
        const signaturePads = {};
        const captchas = {};
        const visibilityRules = <?php 
            $rules = [];
            foreach($fields as $f) {
                $vr = $f['visibility_rules'];
                if(!empty($vr) && (
                    isset($vr['field_idx']) || 
                    (!empty($vr['enabled']) && $vr['enabled'] === true)
                )) {
                    $rules[] = [
                        'target_id' => $f['id'],
                        'rule' => $vr
                    ];
                }
            }
            echo json_encode($rules);
        ?>;
        const fieldMap = <?php 
            $map = [];
            foreach($fields as $idx => $f) {
                $map[$idx] = $f['id'];
            }
            echo json_encode($map);
        ?>;
        const fieldNameMap = <?php 
            $nMap = [];
            foreach($fields as $f) {
                if(!empty($f['name'])) $nMap[$f['name']] = $f['id'];
            }
            echo json_encode($nMap);
        ?>;

        document.addEventListener('DOMContentLoaded', () => {
            initSignatures();
            initCaptchas();
            updateProgress();
            initVisibility();
            initGoogleMaps(); // Moved here to ensure it runs after DOM is loaded
        });

        function initVisibility() {
            // Listen to form changes (delegation)
            const form = document.getElementById('public-form');
            form.addEventListener('change', evaluateVisibility);
            form.addEventListener('input', evaluateVisibility);
            evaluateVisibility(); // Initial check
        }

        function evaluateVisibility() {
            const form = document.getElementById('public-form');
            if(!form) return;
            
            // Ensure composite fields (full_name, time_range) are up to date
            processSpecialFields();

            const data = new FormData(form);
            
            // Map field IDs to current values
            const values = {};
            <?php foreach($fields as $idx => $f): ?>
                values[<?php echo $f['id']; ?>] = data.get('<?php echo $f['name']; ?>');
                // Handle Checkboxes (implode)
                if(data.getAll('<?php echo $f['name']; ?>[]').length > 0) {
                    values[<?php echo $f['id']; ?>] = data.getAll('<?php echo $f['name']; ?>[]').join(',');
                }
            <?php endforeach; ?>

            visibilityRules.forEach(item => {
                let visible = true;
                const rule = item.rule;

                if(rule.field_idx !== undefined) {
                    // Legacy Format - field_idx is an index, we need to map to ID
                    const fieldId = fieldMap[rule.field_idx];
                    visible = checkCondition(values[fieldId], rule.operator, rule.value);
                } else if (rule.enabled) {
                    // Advanced Format
                    if(rule.rules && rule.rules.length > 0) {
                        const results = rule.rules.map(r => {
                            // Builder can save 'field' as INDEX (legacy) or NAME (f_...)
                            let fId;
                            if (typeof r.field === 'string' && r.field.startsWith('f_')) {
                                fId = fieldNameMap[r.field];
                            } else {
                                fId = fieldMap[r.field];
                            }
                            
                            return checkCondition(values[fId], r.op, r.value);
                        });
                        
                        if(rule.match === 'all') visible = results.every(Boolean);
                        else visible = results.some(Boolean);
                    }
                }

                // Toggle Visibility
                const targetContainer = document.querySelector(`.field-container[data-field-id="${item.target_id}"]`);
                if(targetContainer) {
                    if(visible) {
                        targetContainer.classList.remove('hidden');
                        targetContainer.classList.add('animate-in', 'fade-in');
                        // Enable inputs
                        targetContainer.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
                    } else {
                        targetContainer.classList.add('hidden');
                        targetContainer.classList.remove('animate-in', 'fade-in');
                        // Disable inputs to prevent validation/submission
                        targetContainer.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
                    }
                }
            });
        }

        function checkCondition(actual, op, expected) {
            if(actual === undefined || actual === null) actual = '';
            actual = String(actual).toLowerCase().trim();
            expected = String(expected).toLowerCase().trim();
            
            if(op === 'equals') return actual === expected;
            if(op === 'not_equals') return actual !== expected;
            if(op === 'contains') return actual.includes(expected);
            if(op === 'greater') return (parseFloat(actual) || 0) > (parseFloat(expected) || 0);
            if(op === 'less') return (parseFloat(actual) || 0) < (parseFloat(expected) || 0);
            return true;
        }

        function toggleRequired(wrapper, enable) {
            const inputs = wrapper.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if(enable) {
                    if(input.dataset.wasRequired === 'true') input.required = true;
                } else {
                    if(input.required) {
                        input.dataset.wasRequired = 'true';
                        input.required = false;
                    }
                }
            });
        }


        function initCaptchas() {
            document.querySelectorAll('[id^="captcha-text-"]').forEach(el => {
                const n1 = Math.floor(Math.random() * 10);
                const n2 = Math.floor(Math.random() * 10);
                el.innerText = `${n1} + ${n2}`;
                el.dataset.answer = n1 + n2;
            });
        }

        async function searchProducts(input, resultsId) {
            const q = input.value.trim();
            const results = document.getElementById(resultsId);
            if(q.length < 2) { results.classList.add('hidden'); return; }

            const res = await fetch(`api_forms.php?action=search_products&hash=<?php echo $hash; ?>&q=${encodeURIComponent(q)}`);
            const json = await res.json();
            
            if(json.status === 'success' && json.data.length > 0) {
                // Get show_price setting
                const fieldContainer = input.closest('.field-container');
                const fieldId = fieldContainer.dataset.fieldId;
                // We'd need to pass show_price from PHP or fetch it. Let's assume passed via data attribute if needed,
                // but for now let's just use the data we have.
                
                results.innerHTML = json.data.map(p => `
                    <button type="button" onclick="selectProduct('${p.id}', '${p.nombre}', '${resultsId}', '${p.precio || 0}')" class="w-full text-left p-3 hover:bg-indigo-50 rounded-xl transition-all flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 overflow-hidden">
                            <img src="${p.imagen ? 'f.php?path=' + p.imagen : ''}" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-bold text-gray-700">${p.nombre}</p>
                            <p class="text-[9px] text-indigo-500 font-black uppercase">ID: ${p.id}</p>
                        </div>
                    </button>
                `).join('');
                results.classList.remove('hidden');
            } else {
                results.classList.add('hidden');
            }
        }

        function selectProduct(id, name, resultsId, price) {
            const fieldId = resultsId.split('-').pop();
            const container = document.getElementById('selected-products-' + fieldId);
            const input = document.getElementById('input-product-' + fieldId);
            const results = document.getElementById(resultsId);

            let selected = input.value ? JSON.parse(input.value) : [];
            if(!selected.some(p => p.id === id)) {
                selected.push({id, name, price});
                const tag = document.createElement('div');
                tag.className = 'bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-xl text-[10px] flex items-center gap-2 animate-in zoom-in duration-300';
                tag.innerHTML = `${name} <button type="button" onclick="removeProduct('${id}', '${fieldId}')" class="hover:text-red-500">×</button>`;
                tag.id = `prod-tag-${fieldId}-${id}`;
                container.appendChild(tag);
            }
            input.value = JSON.stringify(selected);
            results.classList.add('hidden');
        }

        function removeProduct(id, fieldId) {
            const input = document.getElementById('input-product-' + fieldId);
            let selected = JSON.parse(input.value);
            selected = selected.filter(p => p.id !== id);
            input.value = JSON.stringify(selected);
            document.getElementById(`prod-tag-${fieldId}-${id}`).remove();
        }

        function initSignatures() {
            document.querySelectorAll('canvas[id^="signature-"]').forEach(canvas => {
                const pad = new SignaturePad(canvas);
                signaturePads[canvas.id] = pad;
                
                const resize = () => {
                    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    pad.clear();
                };
                window.addEventListener('resize', resize);
                resize();
            });
        }

        const publicForm = document.getElementById('public-form');
        if(publicForm) {
            publicForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Validate Capthas
                for(let id in captchas) {
                    const val = document.getElementsByName('captcha_' + id)[0].value;
                    if(val != captchas[id]) {
                        alert('Respuesta de seguridad incorrecta.');
                        return;
                    }
                }

                // Prepare Signatures
                for(let id in signaturePads) {
                    const fieldId = id.split('-').pop();
                    const input = document.getElementById('input-signature-' + fieldId);
                    if(input && !signaturePads[id].isEmpty()) {
                        input.value = signaturePads[id].toDataURL();
                    }
                }

                // Process Special Fields (Full Name)
                processSpecialFields();

                const formData = new FormData(publicForm);
                const data = {};
                formData.forEach((value, key) => {
                    if(key.endsWith('[]')) {
                        const realKey = key.slice(0, -2);
                        if(!data[realKey]) data[realKey] = [];
                        data[realKey].push(value);
                    } else {
                        data[key] = value;
                    }
                });

                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const cotId = urlParams.get('cotizacion_id') || '';
                    const otId = urlParams.get('orden_trabajo_id') || '';
                    
                    const response = await fetch(`api_forms.php?action=public_submit&hash=<?php echo $hash; ?>&cotizacion_id=${cotId}&orden_trabajo_id=${otId}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    
                    const res = await response.json();
                    if(res.status === 'success') {
                        // Notify Parent (Compatibility)
                        window.parent.postMessage({action: 'form_submitted'}, '*');

                        // Success Logic
                        publicForm.classList.add('hidden');
                        const successScreen = document.getElementById('success-screen');
                        
                        // Inject Custom HTML if provided, otherwise default
                        if(res.success_html) {
                            successScreen.innerHTML = `
                                <div class="max-w-2xl mx-auto text-center animate-in zoom-in duration-500">
                                    ${res.success_html}
                                    <div class="mt-10">
                                        <button onclick="location.reload()" class="bg-gray-900 text-white font-black uppercase tracking-widest px-10 py-4 rounded-3xl hover:bg-black transition-all shadow-2xl shadow-gray-200">Cerrar</button>
                                    </div>
                                </div>
                            `;
                        }
                        
                        successScreen.classList.remove('hidden');
                        successScreen.classList.add('flex'); // Ensure it's visible
                        window.scrollTo({ top: 0, behavior: 'smooth' });

                        // AUTO-REDIRECT BACK TO QUOTE IF RETURN HASH EXISTS
                        const returnHash = urlParams.get('return_hash');
                        if (returnHash) {
                            setTimeout(() => {
                                window.location.href = `propuesta.php?h=${returnHash}`;
                            }, 2500);
                        }
                    } else {
                        alert('Error al enviar: ' + res.error);
                    }
                } catch(err) {
                    alert('Error de conexión.');
                }
            });
        }

        function changeStep(newStep) {
            if(newStep < 0 || newStep >= totalSteps) return;
            const currentStepEl = document.getElementById(`step-${currentStep}`);
            const inputs = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
            let valid = true;
            inputs.forEach(i => {
                if(!i.value) {
                    i.classList.add('border-red-500', 'bg-red-50');
                    valid = false;
                } else {
                    i.classList.remove('border-red-500', 'bg-red-50');
                }
            });
            if(!valid && newStep > currentStep) {
                alert('Por favor completa los campos obligatorios.');
                return;
            }
            document.getElementById(`step-${currentStep}`).classList.add('hidden');
            document.getElementById(`step-${newStep}`).classList.remove('hidden');
            currentStep = newStep;
            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateProgress() {
            for(let i=0; i<totalSteps; i++) {
                const el = document.getElementById(`progress-bar-${i}`);
                if(!el) continue;
                el.className = `h-full transition-all duration-700 ${i <= currentStep ? 'bg-indigo-500' : 'bg-indigo-500/20'}`;
            }
        }

        function clearSignature(id) {
            if(signaturePads[id]) signaturePads[id].clear();
        }

        function getLocation(inputId) {
            const btn = event.currentTarget;
            btn.classList.add('animate-pulse');
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (p) => {
                        document.getElementById(inputId).value = `${p.coords.latitude}, ${p.coords.longitude}`;
                        btn.classList.replace('bg-indigo-50', 'bg-green-100');
                        btn.classList.replace('text-indigo-600', 'text-green-600');
                        btn.classList.remove('animate-pulse');
                    },
                    () => { alert("No se pudo obtener ubicación."); btn.classList.remove('animate-pulse'); }
                );
            }
        }

        function processSpecialFields() {
            // Concatenate Full Name
            document.querySelectorAll('input[id^="input-full-name-"]').forEach(hiddenInput => {
                const stableName = hiddenInput.dataset.name;
                const name = document.getElementsByName(`${stableName}_name`)[0].value.trim();
                const surname = document.getElementsByName(`${stableName}_surname`)[0].value.trim();
                hiddenInput.value = `${name} ${surname}`.trim();
            });

            // Concatenate Time Range
            document.querySelectorAll('input[id^="input-time-range-"]').forEach(hiddenInput => {
                const fieldId = hiddenInput.id.split('-').pop();
                const stableName = hiddenInput.dataset.name;
                const from = document.getElementById(`time-from-${fieldId}`).value;
                const to = document.getElementById(`time-to-${fieldId}`).value;
                if(from && to) hiddenInput.value = `${from} - ${to}`;
            });
        }

        function updateTimeRange(fieldId, stableName) {
            const from = document.getElementById(`time-from-${fieldId}`).value;
            const to = document.getElementById(`time-to-${fieldId}`).value;
            const hiddenInput = document.getElementById(`input-time-range-${fieldId}`);
            if(from && to) hiddenInput.value = `${from} - ${to}`;
        }

        async function handleFileUpload(input, fieldId) {
            const file = input.files[0];
            if(!file) return;

            const list = document.getElementById(`file-list-${fieldId}`);
            const inputHidden = document.getElementById(`input-file-${fieldId}`);
            
            // UI Feedback
            const item = document.createElement('div');
            item.className = 'p-3 bg-white rounded-xl border border-gray-100 flex items-center justify-between animate-in slide-in-from-top-2';
            item.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[10px] font-black text-gray-700 truncate max-w-[150px]">${file.name}</p>
                        <div class="h-1 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div id="progress-${fieldId}" class="h-full bg-indigo-500 w-0 transition-all"></div>
                        </div>
                    </div>
                </div>
                <button type="button" class="text-gray-300 hover:text-red-500">×</button>
            `;
            list.innerHTML = '';
            list.appendChild(item);

            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload_file');
            formData.append('hash', '<?php echo $hash; ?>');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api_forms.php', true);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    const prog = document.getElementById(`progress-${fieldId}`);
                    if(prog) prog.style.width = percent + '%';
                }
            };

            xhr.onload = () => {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if(res.status === 'success') {
                        inputHidden.value = res.file_path;
                        item.querySelector('button').onclick = () => {
                            list.innerHTML = '';
                            inputHidden.value = '';
                            input.value = '';
                        };
                    } else {
                        alert('Error subiendo archivo: ' + res.error);
                        list.innerHTML = '';
                    }
                } catch(e) {
                    alert('Error en la respuesta del servidor');
                    list.innerHTML = '';
                }
            };

            xhr.send(formData);
        }

        function initCaptchas() {
            document.querySelectorAll('[id^="captcha-text-"]').forEach(el => {
                const n1 = Math.floor(Math.random() * 10);
                const n2 = Math.floor(Math.random() * 10);
                el.innerText = `${n1} + ${n2}`;
                el.dataset.answer = n1 + n2;
            });
        }

        function initGoogleMaps() {
            <?php if($mapsKey): ?>
            document.querySelectorAll('input[id^="address-"]').forEach(el => {
                const autocomplete = new google.maps.places.Autocomplete(el);
                autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                    if(!place.geometry) return;
                    // You could potentially store coordinates too if needed
                });
            });
            <?php endif; ?>
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCaptchas();
            initGoogleMaps();
        });
    </script>


    <?php
    function getIconSvgByName($name) {
        $path = [
            'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            'document-text' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'hashtag' => 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
            'envelope' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'device-mobile' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
            'map-pin' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
            'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'shopping-bag' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
            'shield-check' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-10.618 5.04C5.163 11.411 7.234 16.326 12 21c4.766-4.674 6.837-9.589 10.618-13.016z',
            'pencil' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
            'paper-clip' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
            'photograph' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
            'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
            'heart' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
            'bell' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
            'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            'trash' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
            'check-circle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        ];
        $p = $path[$name] ?? 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4';
        return '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="'.$p.'"></path></svg>';
    }
    ?>
</body>
</html>
