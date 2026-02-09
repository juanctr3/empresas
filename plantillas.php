<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Obtener productos para asociar a plantillas
$empresa_id = getEmpresaId();
$stmt_prod = $pdo->prepare("SELECT id, nombre FROM productos WHERE empresa_id = ?");
$stmt_prod->execute([$empresa_id]);
$productos = $stmt_prod->fetchAll();

// Cargar plantillas de la empresa
$stmt = $pdo->prepare("SELECT p.*, pr.nombre as producto_nombre FROM plantillas p LEFT JOIN productos pr ON p.producto_id = pr.id WHERE p.empresa_id = ? ORDER BY p.nombre ASC");
$stmt->execute([$empresa_id]);
$plantillas = $stmt->fetchAll();

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_plantilla') {
        $nombre = substr(trim($_POST['nombre']), 0, 100); // Limit name length to avoid UI break
        $contenido = $_POST['contenido_html'];
        $tipo = $_POST['tipo'] ?? 'Servicios';
        $producto_id = !empty($_POST['producto_id']) ? $_POST['producto_id'] : null;
        $id = $_POST['id'] ?? 0;

        // Manejo de Logo
        $logo_url = $_POST['logo_actual'] ?? null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                $target_dir = getCompanyUploadPath('plantillas', true);
                $nombre_archivo = 'tpl_logo_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $nombre_archivo)) {
                    $logo_url = getCompanyUploadPath('plantillas') . $nombre_archivo;
                }
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE plantillas SET nombre = ?, contenido_html = ?, tipo = ?, producto_id = ?, logo_url = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$nombre, $contenido, $tipo, $producto_id, $logo_url, $id, $empresa_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO plantillas (empresa_id, nombre, contenido_html, tipo, producto_id, logo_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $nombre, $contenido, $tipo, $producto_id, $logo_url]);
        }
        header("Location: plantillas.php?msg=ok");
        exit;
    }
    
    if ($_POST['accion'] === 'eliminar_plantilla') {
        $stmt = $pdo->prepare("DELETE FROM plantillas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$_POST['id'], $empresa_id]);
        header("Location: plantillas.php?msg=deleted");
        exit;
    }
}

include 'includes/header.php';
?>

<style>
    .ai-magic-btn {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .ai-magic-btn:hover {
        transform: scale(1.05) rotate(1deg);
        box-shadow: 0 15px 30px rgba(168, 85, 247, 0.3);
    }
</style>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Plantillas de Propuesta</h1>
            <p class="text-gray-500 font-medium">Diseña y personaliza el formato de tus cotizaciones con inteligencia artificial.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="openAIModal()" class="ai-magic-btn text-white font-bold py-3 px-6 rounded-2xl shadow-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Generar con IA ✨
            </button>
            <button onclick="editarPlantilla(null)" class="bg-white hover:bg-gray-50 text-gray-900 border border-gray-200 font-bold py-3 px-6 rounded-2xl shadow-sm transition-all active:scale-95 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Diseño Manual
            </button>
        </div>
    </div>

    <!-- Lista de Plantillas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($plantillas as $p): ?>
        <div class="bg-white p-6 rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-xl transition-all group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div class="flex gap-1">
                    <button onclick='editarPlantilla(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)' class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <form method="POST" onsubmit="return confirm('¿Eliminar esta plantilla?')">
                        <input type="hidden" name="accion" value="eliminar_plantilla">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <button class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
            <h3 class="text-xl font-black text-gray-800 mb-1 truncate" title="<?php echo htmlspecialchars($p['nombre']); ?>"><?php echo htmlspecialchars($p['nombre']); ?></h3>
            <?php if($p['producto_nombre']): ?>
                <p class="text-xs font-bold text-blue-500 uppercase tracking-widest mb-1 italic"><?php echo htmlspecialchars($p['producto_nombre']); ?></p>
            <?php endif; ?>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4"><?php echo $p['tipo']; ?></p>
            <div class="pt-4 border-t border-gray-50 flex items-center justify-between">
                <span class="text-[10px] font-bold text-gray-400">Creada: <?php echo date('d/m/Y', strtotime($p['created_at'])); ?></span>
                <span class="text-xs font-black text-blue-600 group-hover:translate-x-1 transition-transform cursor-pointer" onclick='editarPlantilla(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)'>Editar Diseño &rarr;</span>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Card Nueva (Shortcut) -->
        <div onclick="editarPlantilla(null)" class="border-2 border-dashed border-gray-200 p-8 rounded-[2rem] flex flex-col items-center justify-center text-gray-400 hover:border-blue-400 hover:text-blue-500 hover:bg-blue-50/30 transition-all cursor-pointer group">
            <div class="w-12 h-12 rounded-2xl border-2 border-dashed border-current flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <span class="font-bold">Nueva Plantilla</span>
        </div>
    </div>

    <!-- Modal Editor -->
    <div id="modal-plantilla" class="fixed inset-0 z-[60] hidden">
        <div class="flex items-center justify-center h-full">
            <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-md" onclick="cerrarModal()"></div>
            <div class="bg-white shadow-2xl w-full h-full md:w-[95vw] md:h-[95vh] md:rounded-[2.5rem] relative z-10 overflow-hidden flex flex-col">
                <form method="POST" id="form-plantilla" enctype="multipart/form-data" class="flex flex-col h-full">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white shrink-0">
                        <div class="flex items-center gap-4">
                            <div class="hidden md:flex w-12 h-12 bg-blue-600 rounded-2xl items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                            </div>
                            <div>
                                <h2 id="modal-titulo" class="text-xl font-black text-gray-900 leading-none">Nueva Plantilla HTML5</h2>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Editor de Propuestas Premium</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                             <button type="button" onclick="cerrarModal()" class="p-3 hover:bg-gray-100 rounded-2xl transition-all text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
                        <!-- Left Panel: Editor -->
                        <div class="w-full md:w-1/2 flex flex-col border-r border-gray-100 bg-gray-50/50">
                            <div class="p-6 space-y-4 overflow-y-auto custom-scroll">
                                <input type="hidden" name="accion" value="guardar_plantilla">
                                <input type="hidden" name="id" id="plantilla-id">
                                <input type="hidden" name="logo_actual" id="plantilla-logo-actual">
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-2">Nombre de la Plantilla</label>
                                        <input type="text" name="nombre" id="plantilla-nombre" required maxlength="100" class="w-full px-5 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all shadow-sm bg-white" placeholder="Ej: Servicios Premium 2026">
                                        <p class="text-[9px] text-gray-400 mt-1 ml-2">Solo título (max 100 caracteres). El código HTML va en el editor grande abajo.</p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-2">Producto Vinculado (Opcional)</label>
                                        <select name="producto_id" id="plantilla-producto-id" class="w-full px-5 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all shadow-sm bg-white">
                                            <option value="">Cualquier Producto</option>
                                            <?php foreach($productos as $pr): ?>
                                                <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-2">Logo Personalizado (Opcional)</label>
                                        <div class="flex items-center gap-4">
                                            <div id="preview-logo-container" class="hidden w-12 h-12 bg-white rounded-lg border border-gray-200 flex items-center justify-center p-1">
                                                <img id="preview-logo" src="" class="max-w-full max-h-full object-contain">
                                            </div>
                                            <input type="file" name="logo" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 bg-white rounded-xl shadow-sm border border-gray-200">
                                        </div>
                                        <p class="text-[9px] text-gray-400 mt-1 ml-2">Si subes un logo aquí, reemplazará al logo de la empresa para esta plantilla.</p>
                                    </div>

                                    <div class="col-span-2">
                                        <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50">
                                            <h4 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Guía de Diseño Manual
                                            </h4>
                                            <ul class="text-[10px] text-gray-500 space-y-1 font-medium italic">
                                                <li>• Usa clases de <strong>Tailwind CSS</strong> para estilizar (ej: <code class="text-blue-600">text-blue-600</code>).</li>
                                                <li>• El sistema inyectará la tabla de precios donde coloques el tag <code class="text-blue-600 font-bold">{TABLA_PRECIOS}</code>.</li>
                                                <li>• Usa <code class="text-blue-600">page-break-after: always;</code> para saltos de página en PDF.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-2">Variables Dinámicas (Shortcodes)</label>
                                        <div class="flex flex-wrap gap-1.5 px-2">
                                            <button type="button" onclick="insertarTag('{LOGO}')" class="px-3 py-1.5 bg-rose-50 border border-rose-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-rose-600 transition-all font-mono" title="Logo de la empresa">{LOGO}</button>
                                            <button type="button" onclick="insertarTag('{CLIENTE}')" class="px-3 py-1.5 bg-white border border-gray-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-gray-600 transition-all font-mono" title="Nombre del cliente">{CLIENTE}</button>
                                            <button type="button" onclick="insertarTag('{EMPRESA}')" class="px-3 py-1.5 bg-white border border-gray-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-gray-600 transition-all font-mono" title="Tu empresa">{EMPRESA}</button>
                                            <button type="button" onclick="insertarTag('{NUMERO_COT}')" class="px-3 py-1.5 bg-white border border-gray-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-gray-600 transition-all font-mono" title="Nº de Cotización">{NUMERO_COT}</button>
                                            <button type="button" onclick="insertarTag('{TABLA_PRECIOS}')" class="px-3 py-1.5 bg-blue-50 border border-blue-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-blue-600 transition-all font-mono" title="Tabla automática de items">{TABLA_PRECIOS}</button>
                                            <button type="button" onclick="insertarTag('{TOTAL}')" class="px-3 py-1.5 bg-indigo-50 border border-indigo-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-indigo-600 transition-all font-mono" title="Total final">{TOTAL}</button>
                                            <button type="button" onclick="insertarTag('{FECHA_VENCIMIENTO}')" class="px-3 py-1.5 bg-white border border-gray-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-gray-600 transition-all font-mono" title="Vigencia">{FECHA_VENCIMIENTO}</button>
                                            <button type="button" onclick="insertarTag('{NOTAS}')" class="px-3 py-1.5 bg-amber-50 border border-amber-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-amber-600 transition-all font-mono" title="Notas/Condiciones">{NOTAS}</button>
                                            <!-- Nuevos Shortcodes -->
                                            <button type="button" onclick="insertarTag('{LOGOS_CLIENTES}')" class="px-3 py-1.5 bg-green-50 border border-green-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-green-600 transition-all font-mono" title="Grid de logos de confianza">{LOGOS_CLIENTES}</button>
                                            <button type="button" onclick="insertarTag('{REDES_SOCIALES}')" class="px-3 py-1.5 bg-purple-50 border border-purple-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-purple-600 transition-all font-mono" title="Iconos de redes sociales">{REDES_SOCIALES}</button>
                                            <button type="button" onclick="insertarTag('{CONTACTO_NOMBRE}')" class="px-3 py-1.5 bg-white border border-gray-100 shadow-sm hover:border-blue-400 rounded-lg text-[10px] font-bold text-gray-500 transition-all font-mono" title="Nombre Contacto">{CONTACTO_NOMBRE}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Editor Wrapper with Fallback -->
                            <div id="editor-wrapper" class="flex-1 border-t border-gray-100 relative bg-gray-50" style="min-height: 400px;">
                                <textarea name="contenido_html" id="plantilla-contenido" class="w-full h-full p-6 font-mono text-sm text-gray-800 bg-white border-0 outline-none resize-none" placeholder="HTML de la plantilla..."></textarea>
                                <div id="monaco-container" class="absolute inset-0 z-10 hidden"></div>
                            </div>
                        </div>

                        <!-- Right Panel: Live Preview -->
                        <div class="w-full md:w-1/2 flex flex-col bg-gray-100">
                             <div class="p-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] ml-2">Vista Previa Real-Time</span>
                                <div class="flex gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                    <div class="w-2 h-2 rounded-full bg-yellow-400"></div>
                                    <div class="w-2 h-2 rounded-full bg-green-400"></div>
                                </div>
                             </div>
                             <div class="flex-1 bg-white m-4 rounded-2xl shadow-inner overflow-hidden">
                                <iframe id="preview-frame" class="w-full h-full border-0"></iframe>
                             </div>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-100 bg-white flex justify-between items-center shrink-0">
                         <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest hidden md:block">
                            Ctrl + S para guardar cambios rápidos
                         </div>
                        <div class="flex gap-3 w-full md:w-auto">
                            <!-- Botón Eliminar en Editor -->
                            <button type="button" onclick="eliminarDesdeEditor()" id="btn-eliminar-editor" class="hidden px-4 md:px-6 py-3.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-2xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                            <button type="button" onclick="cerrarModal()" class="flex-1 md:flex-none px-8 py-3.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">Cancelar</button>
                            <button type="submit" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white font-black py-3.5 px-10 rounded-2xl shadow-xl shadow-blue-500/20 transition-all active:scale-95 text-sm">GUARDAR PLANTILLA</button>
                        </div>
                    </div>
                </form>
                
                <!-- Form Oculto para Eliminar desde Editor -->
                <form id="form-delete-editor" method="POST" style="display:none;" onsubmit="return confirm('¿Seguro que deseas eliminar esta plantilla definitivamente?')">
                    <input type="hidden" name="accion" value="eliminar_plantilla">
                    <input type="hidden" name="id" id="delete-id-current">
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Generador IA (Nuevo & Espectacular) -->
    <div id="modal-ai-generator" class="fixed inset-0 z-[80] hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-xl" onclick="closeAIModal()"></div>
            <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-4xl relative z-10 overflow-hidden flex flex-col md:flex-row h-[85vh]">
                <!-- Panel de Control IA -->
                <div class="w-full md:w-2/5 p-8 border-r border-gray-100 bg-gray-50/50 overflow-y-auto">
                    <div class="mb-8">
                        <div class="w-12 h-12 ai-magic-btn rounded-2xl flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tighter">AI Designer</h2>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1">Generación Premium de Plantillas</p>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Proveedor de Inteligencia</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="cursor-pointer group">
                                    <input type="radio" name="ai_provider" value="openai" checked class="hidden peer">
                                    <div class="p-3 bg-white border border-gray-200 rounded-xl text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                        <img src="https://openai.com/favicon.ico" class="w-4 h-4 mx-auto mb-1 opacity-60 group-hover:opacity-100">
                                        <span class="text-[9px] font-black">OpenAI</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="ai_provider" value="gemini" class="hidden peer">
                                    <div class="p-3 bg-white border border-gray-200 rounded-xl text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                        <img src="https://www.gstatic.com/lamda/images/favicon_v1_150160d13ff2865fc141.png" class="w-4 h-4 mx-auto mb-1 opacity-60 group-hover:opacity-100">
                                        <span class="text-[9px] font-black">Gemini</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" name="ai_provider" value="claude" class="hidden peer">
                                    <div class="p-3 bg-white border border-gray-200 rounded-xl text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all">
                                        <img src="https://www.anthropic.com/favicon.ico" class="w-4 h-4 mx-auto mb-1 opacity-60 group-hover:opacity-100">
                                        <span class="text-[9px] font-black">Claude</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Producto / Contexto</label>
                            <select id="ai-product-context" class="w-full px-5 py-3 rounded-2xl border border-gray-200 bg-white text-sm font-bold secondary-select">
                                <option value="servicios generales">Empresa General</option>
                                <?php foreach($productos as $pr): ?>
                                    <option value="<?php echo htmlspecialchars($pr['nombre']); ?>"><?php echo htmlspecialchars($pr['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Estilo Visual</label>
                            <select id="ai-style" onchange="updateAIPrompt()" class="w-full px-5 py-3 rounded-2xl border border-gray-200 bg-white text-sm font-bold">
                                <option value="Moderno, Minimalista, Premium">Premium Minimal</option>
                                <option value="Futurista, Dark Mode, High Tech, Cinematic">Cyber Tech (Dark)</option>
                                <option value="Corporativo, Elegante, Serio, Profesional">Classic Corporate</option>
                                <option value="Creativo, Vibrante, Neobrutalista, Startup">Creative / Startup</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Prompt Maestro (Editable)</label>
                            <textarea id="ai-custom-prompt" rows="8" class="w-full px-5 py-4 rounded-2xl border border-gray-200 bg-white text-xs font-medium leading-relaxed outline-none focus:ring-2 focus:ring-blue-500 transition-all custom-scroll" placeholder="Configurando prompt..."></textarea>
                            <p class="text-[9px] text-gray-400 mt-2 italic px-1">Puedes editar este prompt antes de generar para ajustar detalles específicos.</p>
                        </div>

                        <div class="pt-6">
                            <button id="btn-generate-ai" onclick="generateWithAI()" class="w-full ai-magic-btn text-white font-black py-4 rounded-3xl shadow-xl flex items-center justify-center gap-3 active:scale-95">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                MAGIA: GENERAR DISEÑO
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Panel de Resultado IA -->
                <div class="hidden md:flex flex-1 bg-gray-100 flex-col">
                    <div class="p-4 bg-white border-b border-gray-100 flex justify-between items-center shrink-0">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Vista Previa del Resultado</span>
                        <div class="flex gap-2">
                             <div class="w-3 h-3 rounded-full bg-red-400"></div>
                             <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                             <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        </div>
                    </div>
                    <div class="flex-1 bg-white m-6 rounded-3xl shadow-inner overflow-hidden border border-gray-100 relative group">
                        <iframe id="ai-preview-frame" class="w-full h-full border-0"></iframe>
                        <div id="ai-loader" class="absolute inset-0 bg-white/80 backdrop-blur-md hidden flex flex-col items-center justify-center z-20">
                            <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4"></div>
                            <p class="text-xs font-black text-blue-600 animate-pulse">LA IA ESTÁ TRABAJANDO...</p>
                        </div>
                        <div id="ai-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-gray-300">
                             <svg class="w-20 h-20 mb-4 opacity-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11l-8.485 8.485a3 3 0 01-4.243 0l-8.485-8.485a3 3 0 010-4.243l8.485-8.485a3 3 0 014.243 0L19 11zm0 0L9 1m5 4l5 11m-5-5v10m-4-10v10m-1-10v10"></path></svg>
                             <p class="font-bold uppercase tracking-widest text-[10px]">El diseño aparecerá aquí</p>
                        </div>
                    </div>
                    <div class="p-6 bg-white border-t border-gray-100 shrink-0 text-right">
                        <button id="btn-use-ai-result" onclick="useAIResult()" disabled class="px-8 py-3 bg-gray-200 text-gray-500 rounded-2xl text-sm font-black transition-all cursor-not-allowed">USAR ESTA PLANTILLA EN EL EDITOR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cargar Monaco Editor -->
<script>
    var require = { paths: { vs: 'https://unpkg.com/monaco-editor@0.44.0/min/vs' } };
</script>
<script src="https://unpkg.com/monaco-editor@0.44.0/min/vs/loader.js"></script>
 
<script>
let editor;
let timer;
let aiResultHtml = "";
const fallback = document.getElementById('plantilla-contenido');
const monacoContainer = document.getElementById('monaco-container');

// Sincronización Fallback
fallback.addEventListener('input', function() {
    clearTimeout(timer);
    timer = setTimeout(actualizarPreview, 500);
});

require(['vs/editor/editor.main'], function() {
    // Si carga OK
    monacoContainer.classList.remove('hidden');
    fallback.classList.add('hidden'); // Ocultar fallback pero mantener sincronizado

    editor = monaco.editor.create(monacoContainer, {
        value: fallback.value,
        language: 'html',
        theme: 'vs-light',
        fontSize: 14,
        fontFamily: "'Fira Code', 'Monaco', monospace",
        minimap: { enabled: false },
        automaticLayout: true,
        scrollBeyondLastLine: false,
        padding: { top: 20 },
        tabSize: 4,
        wordWrap: 'on'
    });

    editor.onDidChangeModelContent(() => {
        const val = editor.getValue();
        fallback.value = val; // Sincronizar hacia textarea para el POST
        clearTimeout(timer);
        timer = setTimeout(actualizarPreview, 500);
    });
}, function(err) {
    console.warn("Monaco failed to load, keeping fallback textarea.", err);
    // Si falla, el fallback ya está visible por defecto (o lo hacemos visible si estaba hidden)
    monacoContainer.classList.add('hidden');
    fallback.classList.remove('hidden');
});


function openAIModal() {
    document.getElementById('modal-ai-generator').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    updateAIPrompt();
}

function updateAIPrompt() {
    const context = document.getElementById('ai-product-context').value;
    const style = document.getElementById('ai-style').value;
    const promptArea = document.getElementById('ai-custom-prompt');
    
    // Plantilla de prompt basada en la de AIHelper para consistencia
    const basePrompt = `Diseña una PLANTILLA DE COTIZACIÓN COMERCIAL premium.
CONTEXTO: ${context}
ESTILO: ${style}

REGLAS DE DISEÑO:
1. Usa Tailwind CSS para un diseño moderno y espectacular.
2. TIPOGRAFÍA: Evita tamaños exagerados. Títulos max 'text-5xl', Total max 'text-7xl'. Usa 'break-words' para evitar desbordes.
3. Secciones persuasivas: 'Quiénes Somos', 'Nuestro Valor', 'Clientes que confían'.
4. DEBES USAR ESTOS SHORTCODES: {LOGO}, {CLIENTE}, {EMPRESA}, {TABLA_PRECIOS}, {SUBTOTAL}, {IMPUESTOS}, {TOTAL}, {NOTAS}, {REDES_SOCIALES}, {LOGOS_CLIENTES}.
5. Asegúrate que sea apta para imprimir con elegancia.
6. Devuelve solo el código HTML dentro de un div contenedor.`;

    promptArea.value = basePrompt;
}

function closeAIModal() {
    document.getElementById('modal-ai-generator').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function generateWithAI() {
    const provider = document.querySelector('input[name="ai_provider"]:checked').value;
    const context = document.getElementById('ai-product-context').value;
    const style = document.getElementById('ai-style').value;
    const btn = document.getElementById('btn-generate-ai');
    const loader = document.getElementById('ai-loader');
    const placeholder = document.getElementById('ai-placeholder');
    
    btn.disabled = true;
    loader.classList.remove('hidden');
    placeholder.classList.add('hidden');

    const fd = new FormData();
    fd.append('provider', provider);
    fd.append('context', context);
    fd.append('style', style);
    fd.append('prompt', document.getElementById('ai-custom-prompt').value);

    fetch('api_ai_generate.php', { method: 'POST', body: fd })
    .then(res => {
        if (!res.ok) throw new Error("Error de red o servidor (HTTP " + res.status + ")");
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            aiResultHtml = data.html;
            renderAIPreview(aiResultHtml);
            const useBtn = document.getElementById('btn-use-ai-result');
            useBtn.disabled = false;
            useBtn.classList.replace('bg-gray-200', 'bg-blue-600');
            useBtn.classList.replace('text-gray-500', 'text-white');
            useBtn.classList.remove('cursor-not-allowed');
        } else {
            alert("⚠️ Error: " + data.message);
        }
    })
    .catch(err => {
        console.error("AI Error:", err);
        alert("❌ No se pudo conectar con la IA: " + err.message);
    })
    .finally(() => {
        btn.disabled = false;
        loader.classList.add('hidden');
    });
}

function writeToIframe(frame, html) {
    const doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html><html><head>
        <script src="https://cdn.tailwindcss.com"><\/script>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
        <style>body { font-family: 'Outfit', sans-serif; }</style>
        </head><body>${html}</body></html>
    `);
    doc.close();
}

function renderAIPreview(html) {
    const frame = document.getElementById('ai-preview-frame');
    let mockedHtml = html
        .replace(/{LOGO}/g, '<div style="width:120px; height:40px; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#999; font-size:10px; font-weight:black;">TU LOGO</div>')
        .replace(/{CLIENTE}/g, '<span style="color: #2563eb; font-weight: bold;">[CLIENTE DE EJEMPLO]</span>')
        .replace(/{EMPRESA}/g, '<strong>[TU EMPRESA]</strong>')
        .replace(/{TOTAL}/g, '<span style="color: #059669; font-weight: 800;">$9,999.00</span>')
        .replace(/{TABLA_PRECIOS}/g, `
            <table style="width:100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="text-align:left; padding: 10px;">Item</th><th style="text-align:right;">Total</th>
                </tr>
                <tr><td style="padding: 10px;">Servicio de Consultoría</td><td style="text-align:right;">$5,000.00</td></tr>
                <tr><td style="padding: 10px;">Implementación Cloud</td><td style="text-align:right;">$4,999.00</td></tr>
            </table>
        `);
    
    writeToIframe(frame, mockedHtml);
}

function useAIResult() {
    if (!aiResultHtml) return;
    editarPlantilla(null, aiResultHtml); // Pasar el HTML generado directamente
    closeAIModal();
}

function actualizarPreview() {
    // Si tenemos editor, tomamos su valor, si no, del fallback (que ya deben estar sincronizados)
    const html = editor ? editor.getValue() : fallback.value;
    const frame = document.getElementById('preview-frame');
    
    // Al usar fallback, el input hidden ya no es necesario sincronizarlo aquí porque 'fallback' es el textarea que se envía
    // Pero si usamos editor, debemos asegurarnos que fallback tenga el valor
    if(editor) fallback.value = html;

    let mockedHtml = html
        .replace(/{LOGO}/g, '<div style="width:120px; height:40px; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#999; font-size:10px; font-weight:black;">TU LOGO</div>')
        .replace(/{CLIENTE}/g, '<span style="color: #2563eb; font-weight: bold;">[CLIENTE DE EJEMPLO]</span>')
        .replace(/{EMPRESA}/g, '<strong>[TU EMPRESA]</strong>')
        .replace(/{SUBTOTAL}/g, '<span>$8,402.52</span>')
        .replace(/{IMPUESTOS}/g, '<span>$1,596.48</span>')
        .replace(/{TOTAL}/g, '<span style="color: #059669; font-weight: 800;">$10,000.00</span>')
        .replace(/{NOTAS}/g, '<p style="color:#666; font-style:italic;">Esta es una nota de ejemplo para tu propuesta.</p>')
        .replace(/{LOGOS_CLIENTES}/g, '<div style="display:flex;gap:1.5rem;justify-content:center;opacity:0.6;"><div style="background:#ddd;width:40px;height:40px;border-radius:50%;"></div><div style="background:#ddd;width:40px;height:40px;border-radius:50%;"></div><div style="background:#ddd;width:40px;height:40px;border-radius:50%;"></div></div>')
        .replace(/{REDES_SOCIALES}/g, '<div style="display:flex;justify-content:center;gap:15px;margin-top:20px;"><div style="width:40px;height:40px;border-radius:50%;background:#1877F2;"></div><div style="width:40px;height:40px;border-radius:50%;background:#E4405F;"></div><div style="width:40px;height:40px;border-radius:50%;background:#0A66C2;"></div></div>')
        .replace(/{CONTACTO_NOMBRE}/g, 'Juan Pérez')
        .replace(/{TABLA_PRECIOS}/g, `
            <table style="width:100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="border-bottom: 2px solid #eee;">
                    <th style="text-align:left; padding: 10px; font-size:10px; color:#999; text-transform:uppercase;">Descripción</th>
                    <th style="text-align:right; padding: 10px; font-size:10px; color:#999; text-transform:uppercase;">Total</th>
                </tr>
                <tr><td style="padding: 15px 10px; border-bottom:1px solid #f9f9f9; font-weight:bold;">Servicio Premium de Implementación</td><td style="text-align:right; font-weight:black;">$5,000.00</td></tr>
                <tr><td style="padding: 15px 10px; border-bottom:1px solid #f9f9f9; font-weight:bold;">Licencia Empresarial Anual</td><td style="text-align:right; font-weight:black;">$5,000.00</td></tr>
            </table>
        `);

    writeToIframe(frame, mockedHtml);
}

function editarPlantilla(p, forcedContent = null) {
    const modal = document.getElementById('modal-plantilla');
    const titulo = document.getElementById('modal-titulo');
    const id = document.getElementById('plantilla-id');
    const nombre = document.getElementById('plantilla-nombre');
    const prodId = document.getElementById('plantilla-producto-id');
    const logoInput = document.getElementById('plantilla-logo-actual');
    const previewContainer = document.getElementById('preview-logo-container');
    const previewImage = document.getElementById('preview-logo');
    const btnEliminar = document.getElementById('btn-eliminar-editor');
    const deleteIdInput = document.getElementById('delete-id-current');
    
    let content = "";
    if (p) {
        titulo.innerText = "Editar Plantilla";
        id.value = p.id;
        deleteIdInput.value = p.id; // Set ID for deletion
        nombre.value = p.nombre;
        prodId.value = p.producto_id || "";
        logoInput.value = p.logo_url || "";
        
        btnEliminar.classList.remove('hidden'); // Show delete button
        
        if (p.logo_url) {
            previewImage.src = 'f.php?path=' + encodeURIComponent(p.logo_url);
            previewContainer.classList.remove('hidden');
        } else {
            previewContainer.classList.add('hidden');
        }

        content = p.contenido_html;
    } else {
        titulo.innerText = "Nueva Plantilla HTML5";
        id.value = "";
        deleteIdInput.value = "";
        nombre.value = "";
        prodId.value = "";
        logoInput.value = "";
        previewContainer.classList.add('hidden');
        
        btnEliminar.classList.add('hidden'); // Hide delete button
        
        if (forcedContent) {
            content = forcedContent;
            nombre.value = "Nueva Plantilla IA " + new Date().toLocaleTimeString();
        } else {
            content = `<!-- Plantilla Profesional Premium -->
<div class="max-w-4xl mx-auto p-16 bg-white rounded-[3rem] shadow-2xl my-10 border border-gray-50 relative overflow-hidden">
    <div class="absolute -top-20 -right-20 w-64 h-64 bg-blue-50/50 rounded-full blur-3xl"></div>
    
    <header class="flex justify-between items-center mb-20 relative z-10">
        <div>
            {LOGO}
            <h1 class="text-xs font-black tracking-[0.4em] text-blue-600 uppercase mt-4">Propuesta Exclusiva</h1>
        </div>
        <div class="text-right">
            <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Preparado para</p>
            <p class="text-2xl font-black text-gray-900 tracking-tight">{CLIENTE}</p>
        </div>
    </header>

    <main class="space-y-12 relative z-10">
        <div class="prose max-w-none text-gray-600 font-medium leading-relaxed">
            <p>Es un placer para <strong>{EMPRESA}</strong> presentar esta propuesta formal. Hemos diseñado una solución a medida que garantiza resultados excepcionales.</p>
        </div>

        {TABLA_PRECIOS}

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-end">
            <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 italic">Observaciones adicionales:</p>
                <div class="text-xs text-gray-500 bg-gray-50 p-6 rounded-3xl border border-gray-100">
                    {NOTAS}
                </div>
            </div>
            <div class="bg-gray-900 p-10 rounded-[2.5rem] text-white shadow-2xl">
                <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.3em] mb-2">Inversión Final</p>
                <div class="text-5xl font-black tracking-tighter">{TOTAL}</div>
            </div>
        </div>
    </main>

    <footer class="mt-20 pt-10 border-t border-gray-50 text-center">
        <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">© 2026 {EMPRESA} • Todos los derechos reservados</p>
    </footer>
</div>`;
        }
    }

    // Setear contenido
    if (editor) {
        editor.setValue(content);
    }
    fallback.value = content;
    
    setTimeout(actualizarPreview, 100);
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function eliminarDesdeEditor() {
    document.getElementById('form-delete-editor').submit();
}

function cerrarModal() {
    document.getElementById('modal-plantilla').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function insertarTag(tag) {
    if (editor) {
        const position = editor.getPosition();
        editor.executeEdits('', [
            {
                range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                text: tag
            }
        ]);
        editor.focus();
    } else {
        const startPos = fallback.selectionStart;
        const endPos = fallback.selectionEnd;
        fallback.value = fallback.value.substring(0, startPos)
            + tag
            + fallback.value.substring(endPos, fallback.value.length);
        fallback.focus();
        fallback.selectionStart = startPos + tag.length;
        fallback.selectionEnd = startPos + tag.length;
        actualizarPreview();
    }
}

// Shortcut Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        if (!document.getElementById('modal-plantilla').classList.contains('hidden')) {
            e.preventDefault();
            document.getElementById('form-plantilla').submit();
        }
    }
});

function copiarPrompt() {
    const prompt = document.getElementById('prompt-ia').innerText;
    navigator.clipboard.writeText(prompt).then(() => {
        alert('¡Prompt copiado al portapapeles! Pégalo en ChatGPT o Claude.');
    });
}
</script>
