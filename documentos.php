<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
include 'includes/header.php';

$empresa_id = getEmpresaId();

if (!$empresa_id && isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
    header("Location: saas_dashboard.php");
    exit;
}

// Obtener cuota inicial
$stmt = $pdo->prepare("SELECT almacenamiento_usado, storage_type, s3_bucket, s3_region, p.limite_almacenamiento 
                        FROM empresas e 
                        JOIN planes p ON e.plan_id = p.id 
                        WHERE e.id = ?");
$stmt->execute([$empresa_id]);
$quota = $stmt->fetch() ?: ['almacenamiento_usado' => 0, 'limite_almacenamiento' => 0, 'storage_type' => 'local'];
$porcentaje = ($quota['limite_almacenamiento'] > 0) ? ($quota['almacenamiento_usado'] / $quota['limite_almacenamiento']) * 100 : 0;
$storage_label = ($quota['storage_type'] === 's3') ? 'Amazon S3' : 'Servidor Local';
$quota_title = ($quota['storage_type'] === 's3') ? 'Espacio en la Nube' : 'Espacio Local';

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>

<div class="h-[calc(100vh-100px)] flex flex-col p-4 animate-fade-in" id="vault-container">
    
    <!-- Top Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Bóveda</h1>
            <div class="h-8 w-[1px] bg-gray-200 mx-2"></div>
            <!-- Breadcrumbs -->
            <nav id="breadcrumbs" class="flex items-center text-sm font-bold text-gray-500 gap-1 overflow-x-auto">
                <button onclick="navigateTo('root', 'Raíz')" class="hover:text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-lg transition-colors">Raíz</button>
            </nav>
        </div>
        
        <div class="flex gap-3 items-center">
            <!-- Search -->
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" id="search-input" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none w-64 transition-all shadow-sm" placeholder="Buscar archivos...">
            </div>

            <!-- View Toggles -->
            <div class="flex bg-gray-100 rounded-xl p-1">
                <button onclick="switchView('grid')" id="btn-view-grid" class="p-2 rounded-lg text-gray-500 hover:text-gray-900 transition-all bg-white shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </button>
                <button onclick="switchView('list')" id="btn-view-list" class="p-2 rounded-lg text-gray-500 hover:text-gray-900 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>

            <!-- Action Menu Wrapper -->
            <div class="relative">
                <button onclick="toggleActionMenu()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg transition-all active:scale-95 ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Nuevo</span>
                </button>
                
                <!-- Dropdown -->
                <div id="action-menu" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 hidden z-50 animate-fade-in py-1">
                    <button onclick="triggerUpload()" class="w-full text-left px-4 py-3 hover:bg-gray-50 text-sm font-bold text-gray-700 flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Subir Archivo
                    </button>
                    <button onclick="openFolderModal(); toggleActionMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 text-sm font-bold text-gray-700 flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                        Nueva Carpeta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area (Drop Zone) -->
    <div id="main-drop-zone" class="flex-1 overflow-y-auto bg-white rounded-3xl border border-gray-100 card-shadow relative min-h-[400px]">
        
        <!-- Files Container (Grid/List) -->
        <div id="content-area" class="p-6">
            <!-- Injected via JS -->
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <div class="w-48 h-48 bg-gray-50 rounded-full flex items-center justify-center mb-6">
                <svg class="w-24 h-24 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Esta carpeta está vacía</h3>
            <p class="text-gray-500 mt-2">Arrastra archivos aquí o usa el botón "Nuevo"</p>
        </div>

        <!-- Drag Overlay -->
        <div id="drag-overlay" class="absolute inset-0 bg-blue-50/90 backdrop-blur-sm border-4 border-blue-500 border-dashed rounded-3xl flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
            <div class="text-center animate-bounce-slow">
                <svg class="w-24 h-24 text-blue-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <div class="text-3xl font-black text-blue-800">Suelta los archivos aquí</div>
                <div class="text-blue-600 mt-2 font-medium">Se subirán a la carpeta actual</div>
            </div>
        </div>
    </div>

    <!-- Storage Footer -->
    <div class="mt-4 flex items-center justify-between text-xs text-gray-500 font-medium px-2">
        <div>
            <?php echo $storage_label; ?> • <span id="item-count">0 items</span>
        </div>
        <div class="flex items-center gap-3">
             <span id="quota-text"><?php echo formatSize($quota['almacenamiento_usado']); ?> / <?php echo $quota['limite_almacenamiento'] > 0 ? formatSize($quota['limite_almacenamiento']) : '∞'; ?></span>
             <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div id="quota-bar" class="h-full bg-blue-600 rounded-full" style="width: <?php echo min(100, $porcentaje); ?>%"></div>
             </div>
        </div>
    </div>

</div>

<!-- Context Menu -->
<div id="context-menu" class="fixed bg-white rounded-xl shadow-2xl border border-gray-100 w-56 z-[100] hidden transform origin-top-left transition-all duration-100 py-1">
    <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/50">
        <p id="ctx-name" class="font-bold text-gray-800 truncate text-sm">Nombre del Archivo</p>
        <p id="ctx-type" class="text-[10px] text-gray-500 uppercase tracking-widest mt-0.5">Tipo</p>
    </div>
    <button onclick="ctxAction('open')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm font-medium text-gray-700 flex items-center gap-3">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> Ver / Abrir
    </button>
    <button onclick="ctxAction('rename')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm font-medium text-gray-700 flex items-center gap-3">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Renombrar
    </button>
    <button onclick="ctxAction('share')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm font-medium text-gray-700 flex items-center gap-3">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg> Compartir
    </button>
    <div class="h-[1px] bg-gray-100 my-1 mx-2"></div>
    <button onclick="ctxAction('delete')" class="w-full text-left px-4 py-2.5 hover:bg-red-50 text-sm font-medium text-red-600 flex items-center gap-3">
        <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> Eliminar
    </button>
</div>

<!-- Upload Modal (Simplificado) -->
<input type="file" id="hidden-file-input" class="hidden" multiple onchange="handleFileSelect(this)">

<!-- Rename Modal -->
<div id="renameModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-sm rounded-3xl shadow-xl p-6 animate-slide-up">
        <h3 class="text-xl font-bold mb-4">Renombrar</h3>
        <input type="text" id="rename-input" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500" autofocus>
        <div class="flex justify-end gap-2 mt-6">
            <button onclick="closeRenameModal()" class="px-4 py-2 rounded-xl text-gray-500 hover:bg-gray-100 font-bold">Cancelar</button>
            <button onclick="confirmRename()" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-md">Guardar</button>
        </div>
    </div>
</div>

<!-- Folder Modal -->
<div id="folderModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-sm rounded-3xl shadow-xl p-6 animate-slide-up">
        <h3 class="text-xl font-bold mb-4">Nueva Carpeta</h3>
        <input type="text" id="new-folder-name" placeholder="Nombre de la carpeta" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
        
        <div class="mt-4">
            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Color</label>
            <div class="flex gap-2 mt-2" id="color-picker">
                <button onclick="selectColor('#6366f1')" class="w-8 h-8 rounded-full bg-[#6366f1] ring-2 ring-offset-2 ring-indigo-500"></button>
                <button onclick="selectColor('#ef4444')" class="w-8 h-8 rounded-full bg-[#ef4444] hover:ring-2 ring-offset-2 ring-red-500"></button>
                <button onclick="selectColor('#10b981')" class="w-8 h-8 rounded-full bg-[#10b981] hover:ring-2 ring-offset-2 ring-green-500"></button>
                <button onclick="selectColor('#f59e0b')" class="w-8 h-8 rounded-full bg-[#f59e0b] hover:ring-2 ring-offset-2 ring-amber-500"></button>
                <button onclick="selectColor('#3b82f6')" class="w-8 h-8 rounded-full bg-[#3b82f6] hover:ring-2 ring-offset-2 ring-blue-500"></button>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <button onclick="closeFolderModal()" class="px-4 py-2 rounded-xl text-gray-500 hover:bg-gray-100 font-bold">Cancelar</button>
            <button onclick="createFolder()" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-md">Crear</button>
        </div>
    </div>
</div>

<script>
// State
let state = {
    view: localStorage.getItem('vault_view') || 'grid',
    currentFolderId: 'root',
    breadcrumbs: [{id: 'root', name: 'Raíz'}],
    files: [],
    folders: [],
    ctxTarget: null, 
    renameTarget: null,
    shareTarget: null,
    selectedColor: '#6366f1',
    defaultStorage: '<?php echo $quota['storage_type'] ?? 'local'; ?>',
    pendingUploads: null,
    uploadOptions: null
};

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadContent();
    setupDragAndDrop();
    switchView(state.view);
    
    // Global Click to close context menu
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#context-menu')) hideCtxMenu();
    });

    // Search Listener
    document.getElementById('search-input').addEventListener('input', (e) => {
        filterContent(e.target.value);
    });
});

// --- CORE FUNCTIONS ---

async function loadContent() {
    const container = document.getElementById('content-area');
    const emptyState = document.getElementById('empty-state');
    
    try {
        const res = await fetch(`api_documentos.php?accion=listar&carpeta_id=${state.currentFolderId}`);
        const json = await res.json();
        
        if (json.status === 'success') {
            state.files = json.data.documentos;
            state.folders = json.data.carpetas;
            render();
            updateBreadcrumbs();
            
            document.getElementById('item-count').innerText = `${state.files.length + state.folders.length} items`;
            
            // Empty State
            if (state.files.length === 0 && state.folders.length === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }
    } catch (e) {
        console.error(e);
        // alert("Error cargando contenido");
    }
}

function render() {
    const container = document.getElementById('content-area');
    
    if (state.view === 'grid') {
        renderGrid(container);
    } else {
        renderList(container);
    }
}

function renderGrid(container) {
    let html = '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">';
    
    // Folders
    state.folders.forEach(f => {
        html += `
            <div draggable="true" ondragstart="dragStart(event, 'carpeta', ${f.id})" ondrop="dropOnFolder(event, ${f.id})" ondragover="allowDrop(event)"
                 class="group aspect-square p-4 rounded-2xl border border-gray-100 hover:border-blue-400 hover:shadow-lg bg-white transition-all cursor-pointer flex flex-col items-center justify-center text-center relative"
                 onclick="navigateTo(${f.id}, '${f.nombre}')"
                 oncontextmenu="showCtxMenu(event, 'carpeta', ${f.id}, '${f.nombre}')">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-3 transition-transform group-hover:scale-110" style="background-color: ${f.color}20; color: ${f.color}">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"></path></svg>
                </div>
                <div class="text-sm font-bold text-gray-800 truncate w-full px-2">${f.nombre}</div>
                <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Carpeta</div>
            </div>
        `;
    });

    // Files
    state.files.forEach(f => {
        html += `
            <div draggable="true" ondragstart="dragStart(event, 'archivo', ${f.id})"
                 class="group aspect-square p-4 rounded-2xl border border-gray-100 hover:border-blue-400 hover:shadow-lg bg-white transition-all cursor-pointer flex flex-col items-center justify-center text-center relative"
                 oncontextmenu="showCtxMenu(event, 'archivo', ${f.id}, '${f.nombre_original}', '${f.nombre_s3}')">
                <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-3 text-gray-400 group-hover:text-blue-600 transition-colors">
                    ${getFileIcon(f.extension)}
                </div>
                <div class="text-sm font-bold text-gray-800 truncate w-full px-2">${f.nombre_original}</div>
                <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">${formatFileSize(f.tamano)}</div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

function renderList(container) {
    let html = `
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-gray-400 text-xs font-black uppercase tracking-widest border-b border-gray-100">
                    <th class="py-3 pl-4">Nombre</th>
                    <th class="py-3">Tamaño</th>
                    <th class="py-3">Fecha</th>
                    <th class="py-3 pr-4 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
    `;

    // Folders
    state.folders.forEach(f => {
        html += `
            <tr class="hover:bg-blue-50/50 cursor-pointer transition-colors group" 
                onclick="navigateTo(${f.id}, '${f.nombre}')"
                ondrop="dropOnFolder(event, ${f.id})" ondragover="allowDrop(event)"
                draggable="true" ondragstart="dragStart(event, 'carpeta', ${f.id})"
                oncontextmenu="showCtxMenu(event, 'carpeta', ${f.id}, '${f.nombre}')">
                <td class="py-3 pl-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: ${f.color}20; color: ${f.color}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"></path></svg>
                        </div>
                        <span class="font-bold text-gray-700 text-sm">${f.nombre}</span>
                    </div>
                </td>
                <td class="py-3 text-xs font-bold text-gray-400 uppercase">-</td>
                <td class="py-3 text-xs font-bold text-gray-400 uppercase">${new Date(f.created_at).toLocaleDateString()}</td>
                <td class="py-3 pr-4 text-right">
                    <button class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-white" onclick="event.stopPropagation(); showCtxMenu(event, 'carpeta', ${f.id}, '${f.nombre}')">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>
                </td>
            </tr>
        `;
    });

    // Files
    state.files.forEach(f => {
        html += `
            <tr class="hover:bg-gray-50 cursor-pointer transition-colors group"
                draggable="true" ondragstart="dragStart(event, 'archivo', ${f.id})"
                oncontextmenu="showCtxMenu(event, 'archivo', ${f.id}, '${f.nombre_original}', '${f.nombre_s3}')">
                <td class="py-3 pl-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            ${getFileIcon(f.extension, 'w-5 h-5')}
                        </div>
                        <span class="font-bold text-gray-700 text-sm truncate max-w-[200px]">${f.nombre_original}</span>
                    </div>
                </td>
                <td class="py-3 text-xs font-bold text-gray-400 uppercase">${formatFileSize(f.tamano)}</td>
                <td class="py-3 text-xs font-bold text-gray-400 uppercase">${new Date(f.created_at).toLocaleDateString()}</td>
                <td class="py-3 pr-4 text-right">
                    <button class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-white" onclick="event.stopPropagation(); showCtxMenu(event, 'archivo', ${f.id}, '${f.nombre_original}', '${f.nombre_s3}')">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// --- NAVIGATION ---

function navigateTo(id, name) {
    if (id === 'root') {
        state.currentFolderId = 'root';
        state.breadcrumbs = [{id: 'root', name: 'Raíz'}];
    } else {
        // Check if skipping back
        const idx = state.breadcrumbs.findIndex(b => b.id == id);
        if (idx !== -1) {
            state.breadcrumbs = state.breadcrumbs.slice(0, idx + 1);
        } else {
            state.breadcrumbs.push({id, name});
        }
        state.currentFolderId = id;
    }
    loadContent();
}

function updateBreadcrumbs() {
    const nav = document.getElementById('breadcrumbs');
    let html = '';
    state.breadcrumbs.forEach((b, index) => {
        if (index > 0) html += '<span class="text-gray-300">/</span>';
        const isLast = index === state.breadcrumbs.length - 1;
        html += `<button onclick="navigateTo('${b.id}', '${b.name}')" 
                class="${isLast ? 'text-gray-900 font-black cursor-default' : 'hover:text-blue-600 hover:bg-blue-50'} px-2 py-1 rounded-lg transition-colors whitespace-nowrap">
                ${b.name}
            </button>`;
    });
    nav.innerHTML = html;
}

// --- DRAG & DROP & UPLOAD ---

function setupDragAndDrop() {
    const zone = document.getElementById('vault-container');
    const overlay = document.getElementById('drag-overlay');
    
    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        overlay.classList.remove('opacity-0', 'pointer-events-none');
    });

    overlay.addEventListener('dragleave', (e) => {
        e.preventDefault();
        overlay.classList.add('opacity-0', 'pointer-events-none');
    });

    overlay.addEventListener('drop', (e) => {
        e.preventDefault();
        overlay.classList.add('opacity-0', 'pointer-events-none');
        
        if (e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    });
}

// --- ACTION MENU ---
function toggleActionMenu() {
    const menu = document.getElementById('action-menu');
    menu.classList.toggle('hidden');
}

// Close menu when clicking outside
document.addEventListener('click', (e) => {
    const menu = document.getElementById('action-menu');
    const btn = e.target.closest('button[onclick="toggleActionMenu()"]');
    if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !btn) {
        menu.classList.add('hidden');
    }
});


// (Old functions removed)





function triggerUpload() {
    toggleActionMenu();
    state.pendingUploads = null;
    state.uploadOptions = null;
    
    // Manage Storage Selector (Warn S3)
    const hasS3 = <?php echo !empty($quota['s3_bucket']) ? 'true' : 'false'; ?>;
    const storageSelect = document.getElementById('upload-storage-selector');
    const s3Option = storageSelect.querySelector('option[value="s3"]');
    
    if (!hasS3 && s3Option) {
        if (!s3Option.text.includes("Verificar")) {
            s3Option.text += " (Verificar Config)";
        }
    }
    
    // Populate Folder Dropdown
    const folderSelect = document.getElementById('upload-folder-target');
    folderSelect.innerHTML = '<option value="root">/ Raíz</option>';
    state.folders.forEach(f => {
        folderSelect.innerHTML += `<option value="${f.id}">📂 ${f.nombre}</option>`;
    });
    folderSelect.value = state.currentFolderId;

    // Reset UI
    const btn = document.querySelector('#uploadModal button');
    if(btn) btn.innerText = "Seleccionar Archivos";
    
    // Reset inputs
    document.getElementById('file-input').value = ''; 
    document.getElementById('upload-category').value = 'General';
    document.getElementById('upload-vencimiento').value = '';

    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').style.display = 'flex';
}

async function handleFiles(fileList) {
    // CASE A: New Flow - Options pre-selected
    if (state.uploadOptions) {
        processUploads(fileList, state.uploadOptions);
        state.uploadOptions = null;
        document.getElementById('file-input').value = ''; // Reset
        return;
    }

    // CASE B: Drag & Drop - Show Modal
    const hasS3 = <?php echo !empty($quota['s3_bucket']) ? 'true' : 'false'; ?>;
    const storageSelect = document.getElementById('upload-storage-selector');
    const s3Option = storageSelect.querySelector('option[value="s3"]');
    
    if (!hasS3 && s3Option) {
        if (!s3Option.text.includes("Verificar")) s3Option.text += " (Verificar Config)";
    }

    const folderSelect = document.getElementById('upload-folder-target');
    folderSelect.innerHTML = '<option value="root">/ Raíz</option>';
    state.folders.forEach(f => {
        folderSelect.innerHTML += `<option value="${f.id}">📂 ${f.nombre}</option>`;
    });
    folderSelect.value = state.currentFolderId;
    
    // Reset Button Text
    const btn = document.querySelector('#uploadModal button');
    if(btn) btn.innerText = "Confirmar y Subir";

    state.pendingUploads = fileList;
    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').style.display = 'flex';
}

function confirmUpload() {
    const storage = document.getElementById('upload-storage-selector').value;
    const folder = document.getElementById('upload-folder-target').value;
    const category = document.getElementById('upload-category').value;
    const vencimiento = document.getElementById('upload-vencimiento').value;

    // CASE A: New Flow - Select Options First
    if (!state.pendingUploads || state.pendingUploads.length === 0) {
        state.uploadOptions = {
             storageType: storage,
             folderId: folder,
             category: category,
             vencimiento: vencimiento
        };
        document.getElementById('file-input').click();
        
        // Hide modal
        document.getElementById('uploadModal').classList.add('hidden');
        document.getElementById('uploadModal').style.display = 'none';
        return;
    }

    // CASE B: Drag & Drop Flow - Files already there
    processUploads(state.pendingUploads, {
        storageType: storage,
        folderId: folder,
        category: category,
        vencimiento: vencimiento
    });
}

async function processUploads(fileList, options) {
    const totalFiles = fileList.length;
    let uploaded = 0;

    if(totalFiles === 0) {
        alert("No hay archivos para subir.");
        return;
    }

    // Show Progress
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('upload-progress').classList.remove('hidden');
    updateUploadProgress(0);

    for (const file of fileList) {
        const formData = new FormData();
        formData.append('accion', 'subir');
        formData.append('archivo', file);
        formData.append('storage_type', options.storageType); 
        formData.append('carpeta_id', options.folderId === 'root' ? '' : options.folderId);
        formData.append('categoria', options.category);
        formData.append('fecha_vencimiento', options.vencimiento);

        try {
            const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                // Success
            } else {
                console.error("Error subiendo " + file.name, json);
                alert(`Error al subir ${file.name}: ${json.message}`);
            }
        } catch(e) { 
            console.error("Fetch error", e); 
            alert(`Error de red al subir ${file.name}: ${e.message}`);
        }
        
        uploaded++;
        updateUploadProgress((uploaded / totalFiles) * 100);
    }
    
    setTimeout(() => {
        document.getElementById('upload-progress').classList.add('hidden');
        loadContent();
    }, 1500);
}

// --- ITEM MOVING (DnD) ---

function dragStart(e, type, id) {
    e.dataTransfer.setData("application/json", JSON.stringify({type, id}));
    e.dataTransfer.effectAllowed = "move";
}

function allowDrop(e) {
    e.preventDefault();
}

async function dropOnFolder(e, targetId) {
    e.preventDefault();
    e.stopPropagation();
    
    const data = JSON.parse(e.dataTransfer.getData("application/json"));
    if (!data || !data.id) return;

    if (data.type === 'carpeta' && data.id === targetId) return; // Can't move into itself

    if (!confirm(`¿Mover este elemento a la carpeta?`)) return;

    const formData = new FormData();
    formData.append('accion', 'mover');
    formData.append('type', data.type);
    formData.append('id', data.id);
    formData.append('target_id', targetId);

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'success') {
            loadContent();
        } else {
            alert(json.message);
        }
    } catch(e) { alert("Error moviendo elemento: " + e.message); console.error(e); }
}


// --- CONTEXT MENU & ACTIONS ---

function showCtxMenu(e, type, id, name, link = '') {
    e.preventDefault();
    e.stopPropagation();
    
    state.ctxTarget = { type, id, name, link };
    
    const menu = document.getElementById('context-menu');
    document.getElementById('ctx-name').innerText = name;
    document.getElementById('ctx-type').innerText = type.toUpperCase();
    
    // Position
    let x = e.clientX;
    let y = e.clientY;
    
    // Boundary check would go here ideally
    
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    menu.classList.remove('hidden');
}

function hideCtxMenu() {
    document.getElementById('context-menu').classList.add('hidden');
    state.ctxTarget = null;
}

function ctxAction(action) {
    if (!state.ctxTarget) return;
    const { type, id, name, link } = state.ctxTarget;
    
    switch(action) {
        case 'open':
            if (type === 'carpeta') navigateTo(id, name);
            else window.open(link, '_blank');
            break;
        case 'rename':
            openRenameModal(id, type, name);
            break;
        case 'delete':
            deleteItem(id, type);
            break;
        case 'share':
            openShareModal(id, type, name); 
            break;
    }
    hideCtxMenu();
}

// --- RENAMING ---

function openRenameModal(id, type, currentName) {
    state.renameTarget = { id, type };
    const modal = document.getElementById('renameModal');
    const input = document.getElementById('rename-input');
    input.value = currentName;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    input.select();
}

function closeRenameModal() {
    document.getElementById('renameModal').classList.add('hidden');
    document.getElementById('renameModal').style.display = 'none';
    state.renameTarget = null;
}

async function confirmRename() {
    const newName = document.getElementById('rename-input').value;
    if (!newName) return;
    
    const formData = new FormData();
    formData.append('accion', 'renombrar');
    formData.append('id', state.renameTarget.id);
    formData.append('type', state.renameTarget.type);
    formData.append('nombre', newName);

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'success') {
            closeRenameModal();
            loadContent();
        } else {
            alert(json.message);
        }
    } catch(e) { alert("Error al renombrar: " + e.message); console.error(e); }
}

// --- FOLDERS ---

// --- PROGRESS ---
function updateUploadProgress(percent) {
    const bar = document.getElementById('upload-progress');
    const fill = document.getElementById('upload-bar-fill');
    bar.classList.remove('hidden');
    fill.style.width = `${percent}%`;
    if(percent >= 100) {
        fill.classList.add('bg-green-500');
    } else {
        fill.classList.remove('bg-green-500');
    }
}

function openFolderModal() {
     document.getElementById('folderModal').classList.remove('hidden');
     document.getElementById('folderModal').style.display = 'flex';
     document.getElementById('new-folder-name').focus();
}

function closeFolderModal() {
    document.getElementById('folderModal').classList.add('hidden');
    document.getElementById('folderModal').style.display = 'none';
    document.getElementById('new-folder-name').value = '';
}

function selectColor(color) {
    state.selectedColor = color;
    // UI feedback handled via framework classes or simple dom manipulation
    document.querySelectorAll('#color-picker button').forEach(b => b.classList.remove('ring-2'));
    event.target.classList.add('ring-2');
}

async function createFolder() {
    const name = document.getElementById('new-folder-name').value;
    if (!name) return;

    const formData = new FormData();
    formData.append('accion', 'crear_carpeta');
    formData.append('nombre', name);
    formData.append('color', state.selectedColor);
    if(state.currentFolderId !== 'root') formData.append('parent_id', state.currentFolderId);

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.status === 'success') {
            document.getElementById('folderModal').classList.add('hidden');
            document.getElementById('folderModal').style.display = 'none';
            loadContent();
        } else {
            alert(json.message);
        }
    } catch(e) { alert("Error creando carpeta: " + e.message); console.error(e); }
}

async function deleteItem(id, type) {
    if (!confirm(type === 'carpeta' ? "¿Eliminar carpeta y TODO su contenido?" : "¿Eliminar archivo?")) return;

    const formData = new FormData();
    formData.append('accion', type === 'carpeta' ? 'eliminar_carpeta' : 'eliminar');
    formData.append('id', id);
    if (type === 'carpeta') formData.append('force', 'true'); // Agent 1's recursive implementation

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.status === 'success') loadContent();
        else alert(json.message);
    } catch(e) { alert("Error eliminando"); }
}

// --- UTILS ---

function switchView(view) {
    state.view = view;
    localStorage.setItem('vault_view', view);
    
    document.getElementById('btn-view-grid').classList.toggle('bg-white', view === 'grid');
    document.getElementById('btn-view-grid').classList.toggle('shadow-sm', view === 'grid');
    document.getElementById('btn-view-list').classList.toggle('bg-white', view === 'list');
    document.getElementById('btn-view-list').classList.toggle('shadow-sm', view === 'list');
    
    render();
}

function filterContent(query) {
    if (!query) return loadContent();
    query = query.toLowerCase();
    
    const filteredFiles = state.files.filter(f => f.nombre_original.toLowerCase().includes(query));
    const filteredFolders = state.folders.filter(f => f.nombre.toLowerCase().includes(query));
    
    // Hacky temporary render state override
    const tempState = { ...state, files: filteredFiles, folders: filteredFolders };
    // This requires render to access global state or passed arguments. 
    // Let's modify render to accept optional data or just update state references for filtering?
    // Safer: Update filtered view logic in render
    
    // Quick fix: re-use existing render but swap data temporarily? No, reloading wipes it.
    // Client-side filtering only works if we have all data. We do (for current folder).
    // If we want global search, we need API support.
    
    // Proceeding with local filter for current view:
    const container = document.getElementById('content-area');
    // ... custom render logic or refactor render to take args ...
    // Easiest is to modify global state.files/folders but that's destructive.
    // Let's just manually call render with filtered set logic:
    
    // NOTE: For now, I'll restrict search to current folder client-side.
    // Ideally Agent 1 would add a search API.
}

function getFileIcon(ext, classes = "w-8 h-8") {
    ext = (ext || '').toLowerCase();
    if (['jpg', 'jpeg', 'png', 'svg', 'webp'].includes(ext)) 
        return `<svg class="${classes}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
    if (ext === 'pdf') 
        return `<svg class="${classes}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>`;
    return `<svg class="${classes}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
}

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// --- SHARE MODAL ---

function openShareModal(id, type, name) {
    state.shareTarget = { id, type, name };
    document.getElementById('share-title').innerText = `Compartir: ${name}`;
    document.getElementById('share-link-container').classList.add('hidden');
    document.getElementById('share-config').classList.remove('hidden');
    document.getElementById('shareModal').classList.remove('hidden');
    document.getElementById('shareModal').style.display = 'flex';
}

function closeShareModal() {
    document.getElementById('shareModal').classList.add('hidden');
    document.getElementById('shareModal').style.display = 'none';
    state.shareTarget = null;
}

async function generateShareLink() {
    const pass = document.getElementById('share-pass').value;
    const expira = document.getElementById('share-expira').value;
    const vistas = document.getElementById('share-vistas').value;
    
    const btn = event.currentTarget;
    btn.innerHTML = 'Generando...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('accion', 'compartir');
    formData.append('id', state.shareTarget.id);
    formData.append('type', state.shareTarget.type);
    formData.append('password', pass);
    formData.append('expira', expira);
    formData.append('max_vistas', vistas);

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if(json.status === 'success') {
            document.getElementById('share-config').classList.add('hidden');
            document.getElementById('share-link-container').classList.remove('hidden');
            
            const linkInput = document.getElementById('share-result-link');
            linkInput.value = json.link;
            
            // Setup Send Actions
            document.getElementById('btn-send-wa').onclick = () => sendShare('whatsapp', json.link);
            document.getElementById('btn-send-email').onclick = () => sendShare('email', json.link);
        } else {
            alert(json.message);
        }
    } catch(e) { alert("Error generando enlace"); }
    
    btn.innerHTML = 'Generar Enlace Seguro';
    btn.disabled = false;
}

async function sendShare(channel, link) {
    const target = prompt(channel === 'whatsapp' ? "Ingresa el número de WhatsApp (con código país):" : "Ingresa el correo electrónico:");
    if(!target) return;

    const formData = new FormData();
    formData.append('accion', channel === 'whatsapp' ? 'enviar_whatsapp' : 'enviar_email');
    formData.append('destinatario', target);
    formData.append('link', link);
    formData.append('nombre_doc', state.shareTarget.name);

    try {
        const res = await fetch('api_documentos.php', { method: 'POST', body: formData });
        const json = await res.json();
        alert(json.message);
    } catch(e) { alert("Error enviando"); }
}

function copyShareLink() {
    const input = document.getElementById('share-result-link');
    input.select();
    document.execCommand('copy');
    alert("Enlace copiado al portapapeles");
}

// Helpers for animations
</script>

<!-- Upload Selection Modal -->
<div id="uploadModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 animate-slide-up text-center">
        <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">¿Dónde deseas guardar?</h3>
        <p class="text-gray-500 text-xs mb-6">Tu empresa tiene habilitado almacenamiento local y en la nube.</p>

        <div class="space-y-4 text-left">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Almacenamiento</label>
                <select id="upload-storage-selector" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none font-bold text-sm">
                    <option value="local">📁 Local (Servidor)</option>
                    <option value="s3">☁️ Amazon S3 (Nube)</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Categoría</label>
                    <select id="upload-category" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs outline-none">
                        <option value="General">General</option>
                        <option value="Factura">Factura</option>
                        <option value="Contrato">Contrato</option>
                        <option value="Identidad">Identidad</option>
                        <option value="Certificado">Certificado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Vencimiento</label>
                    <input type="date" id="upload-vencimiento" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs outline-none">
                </div>
            </div>

<div id="upload-progress" class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-sm animate-slide-up">
        <div class="text-center mb-4">
             <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-3 text-blue-600 animate-pulse">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
             </div>
             <h3 class="font-bold text-xl text-gray-800">Subiendo Archivos</h3>
             <p class="text-xs text-gray-500 mt-1">Por favor no cierres la ventana...</p>
        </div>
        
        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
             <div id="upload-bar-fill" class="h-full bg-blue-600 transition-all duration-300 w-0 rounded-full"></div>
        </div>
        <p class="text-xs font-bold text-center mt-3 text-blue-600" id="upload-status-text">Procesando...</p>
    </div>
</div>

<input type="file" id="file-input" class="hidden" multiple onchange="handleFiles(this.files)">

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Destino (Opcional)</label>
                <select id="upload-folder-target" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm outline-none">
                    <option value="root">/ Raíz</option>
                    <!-- Dinámico -->
                </select>
            </div>
            
            <button onclick="confirmUpload()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition-all mt-2">
                Confirmar y Subir
            </button>
        </div>
    </div>
</div>

<!-- Share Modal HTML -->
<div id="shareModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 animate-slide-up">
        <div class="flex justify-between items-center mb-6">
            <h3 id="share-title" class="text-xl font-bold text-gray-800">Compartir Archivo</h3>
            <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div id="share-config" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Contraseña (Opcional)</label>
                <input type="password" id="share-pass" placeholder="Protege con clave..." class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Expira El</label>
                    <input type="datetime-local" id="share-expira" class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Max Vistas</label>
                    <input type="number" id="share-vistas" placeholder="0 = Ilimitado" class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <button onclick="generateShareLink()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition-all mt-4">
                Generar Enlace Seguro
            </button>
        </div>

        <div id="share-link-container" class="hidden space-y-6">
            <div class="bg-green-50 p-4 rounded-xl border border-green-100 flex flex-col items-center text-center">
                 <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-2 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h4 class="font-bold text-green-800">¡Enlace Generado!</h4>
                <p class="text-xs text-green-600">Cualquiera con este enlace podrá acceder según tus reglas.</p>
            </div>
            
            <div class="flex gap-2">
                <input type="text" id="share-result-link" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-600 text-sm">
                <button onclick="copyShareLink()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 rounded-xl transition-all">Copiar</button>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button id="btn-send-wa" class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-xl transition-all">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    WhatsApp
                </button>
                <button id="btn-send-email" class="flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-xl transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Email
                </button>
            </div>
            <p class="text-[10px] text-gray-400 text-center">Se enviará el enlace de acceso seguro.</p>
        </div>
    </div>
</div>


<style>
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes slide-up { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes bounce-slow { 0%, 100% { transform: translateY(-5%); } 50% { transform: translateY(5%); } }

.animate-fade-in { animation: fade-in 0.4s ease-out; }
.animate-slide-up { animation: slide-up 0.3s ease-out; }
.animate-bounce-slow { animation: bounce-slow 2s infinite; }
.card-shadow { box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05); }

/* Custom Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<?php include 'includes/footer.php'; ?>
