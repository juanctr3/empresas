<?php
session_start();
require_once 'includes/auth_helper.php';
require_once 'includes/header.php';

$current_page = 'formularios.php';
?>

<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-800">Generador de Formularios</h1>
            <p class="text-sm text-gray-500 font-medium">Crea formularios dinámicos, encuestas y captadores de datos.</p>
        </div>
        <button onclick="openCreateModal()" class="flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:shadow-xl transition-all active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nuevo Formulario
        </button>
    </div>

    <!-- Stats & Filters (Optional) -->
    
    <!-- Forms Grid -->
    <div id="forms-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Loader -->
        <div class="col-span-full flex justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div id="create-modal" class="fixed inset-0 z-50 hidden">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" onclick="closeCreateModal()"></div>
    
    <!-- Modal -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 transform transition-all scale-100">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Nuevo Formulario</h3>
        <form id="create-form">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Título del Formulario</label>
                    <input type="text" name="titulo" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all" placeholder="Ej: Encuesta de Satisfacción">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Descripción (Opcional)</label>
                    <textarea name="descripcion" rows="3" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all resize-none" placeholder="Breve descripción..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="button" onclick="closeCreateModal()" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-colors">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-md shadow-indigo-100 transition-colors">Crear</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="share-modal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeShareModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-[2.5rem] shadow-2xl overflow-hidden p-8 transform transition-all scale-100">
        <div class="flex justify-between items-start mb-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900 tracking-tight">Compartir Formulario</h3>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-widest mt-1" id="share-form-title">Nombre del Formulario</p>
            </div>
            <button onclick="closeShareModal()" class="p-2 text-slate-300 hover:text-rose-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="space-y-6">
            <!-- Link Directo -->
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Enlace Público Directo</label>
                <div class="flex gap-2">
                    <input type="text" id="share-url" readonly class="flex-1 bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3 text-xs font-mono text-slate-600 outline-none">
                    <button onclick="copyToClipboard('share-url')" class="bg-indigo-600 text-white px-5 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all active:scale-95">Copiar</button>
                </div>
            </div>

            <!-- Iframe -->
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Código para Insertar (Iframe)</label>
                <div class="flex gap-2">
                    <input type="text" id="share-iframe" readonly class="flex-1 bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3 text-xs font-mono text-slate-600 outline-none">
                    <button onclick="copyToClipboard('share-iframe')" class="bg-slate-900 text-white px-5 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all active:scale-95">Copiar</button>
                </div>
            </div>

            <!-- Redes Rápidas -->
            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-50">
                <a href="#" id="share-wa" target="_blank" class="flex items-center justify-center gap-3 py-4 bg-green-500 text-white rounded-3xl font-black text-xs uppercase tracking-widest hover:bg-green-600 hover:shadow-xl hover:shadow-green-100 transition-all group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.27 9.27 0 01-4.487-1.164l-.322-.19-3.338.875.89-3.251-.208-.332A9.28 9.28 0 012.225 9.37C2.228 4.225 6.42 0 11.57 0a11.5 11.5 0 018.175 3.385 11.455 11.455 0 013.387 8.19c-.002 5.143-4.194 9.366-9.345 9.366z"/></svg>
                    WhatsApp
                </a>
                <a href="#" id="share-email" class="flex items-center justify-center gap-3 py-4 bg-indigo-50 text-indigo-600 rounded-3xl font-black text-xs uppercase tracking-widest hover:bg-indigo-100 transition-all group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    E-mail
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Menu Logic
let activeMenu = null;

function toggleMenu(event, id) {
    if(event) event.stopPropagation();
    const menu = document.getElementById(`menu-${id}`);
    
    if (activeMenu && activeMenu !== menu) {
        activeMenu.classList.add('hidden');
    }
    
    if (menu) {
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            activeMenu = menu;
        } else {
            menu.classList.add('hidden');
            activeMenu = null;
        }
    }
}

function duplicateForm(id) {
    alert('Función de duplicar próximamente');
}

function deleteForm(id, title) {
    if(confirm(`¿Estás seguro de eliminar el formulario "${title}"? esta acción no se puede deshacer.`)) {
        fetch(`api_forms.php?id=${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                loadForms();
            } else {
                alert('Error al eliminar');
            }
        });
    }
}

// Modal Logic
function openCreateModal() { 
    const modal = document.getElementById('create-modal');
    modal.classList.remove('hidden'); 
}
function closeCreateModal() { 
    const modal = document.getElementById('create-modal');
    modal.classList.add('hidden'); 
}

function shareForm(id, hash, titulo) {
    const publicUrl = `${window.location.origin}${window.location.pathname.replace('formularios.php', 'public_form.php')}?hash=${hash}`;
    const iframeCode = `<iframe src="${publicUrl}" width="100%" height="600px" frameborder="0"></iframe>`;
    
    document.getElementById('share-form-title').innerText = titulo;
    document.getElementById('share-url').value = publicUrl;
    document.getElementById('share-iframe').value = iframeCode;
    
    document.getElementById('share-wa').href = `https://wa.me/?text=${encodeURIComponent('Por favor completa este formulario: ' + publicUrl)}`;
    document.getElementById('share-email').href = `mailto:?subject=${encodeURIComponent('Formulario: ' + titulo)}&body=${encodeURIComponent('Hola, por favor completa el siguiente formulario: ' + publicUrl)}`;
    
    document.getElementById('share-modal').classList.remove('hidden');
}

function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
}

function copyToClipboard(id) {
    const input = document.getElementById(id);
    input.select();
    document.execCommand('copy');
    
    const btn = event.target;
    const originalText = btn.innerText;
    btn.innerText = '¡Copiado!';
    btn.classList.replace('bg-indigo-600', 'bg-emerald-500');
    btn.classList.replace('bg-slate-900', 'bg-emerald-500');
    
    setTimeout(() => {
        btn.innerText = originalText;
        btn.classList.replace('bg-emerald-500', 'bg-indigo-600');
        btn.classList.replace('bg-emerald-500', 'bg-slate-900');
    }, 2000);
}
function loadForms() {
    const grid = document.getElementById('forms-grid');
    
    fetch('api_forms.php')
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                renderForms(res.data);
            } else {
                grid.innerHTML = `<div class="col-span-full text-center text-red-500 font-bold py-10">Error al cargar formularios</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            grid.innerHTML = `<div class="col-span-full text-center text-gray-400 font-medium py-10">Error de conexión</div>`;
        });
}

function renderForms(forms) {
    const grid = document.getElementById('forms-grid');
    grid.innerHTML = '';
    
    if(forms.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-4 text-indigo-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">No hay formularios</h3>
                <p class="text-sm text-gray-400 mt-1 max-w-xs">Crea tu primer formulario para empezar a recopilar datos.</p>
                <button onclick="openCreateModal()" class="mt-6 text-indigo-600 font-bold hover:underline">Crear Formulario</button>
            </div>
        `;
        return;
    }

    forms.forEach(form => {
        const div = document.createElement('div');
        div.className = 'group relative bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300';
        div.innerHTML = `
            <div class="p-6">
                <!-- Status & Menu -->
                <div class="flex items-start justify-between mb-4">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${form.is_active != '0' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}">
                        ${form.is_active != '0' ? 'Activo' : 'Inactivo'}
                    </span>
                    <div class="relative">
                        <button class="text-gray-400 hover:text-gray-600 transition-colors p-1" onclick="toggleMenu(event, ${form.id})">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                        <!-- Dropdown Menu -->
                        <div id="menu-${form.id}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-200 origin-top-right">
                            <a href="formularios_builder.php?id=${form.id}" class="block px-4 py-3 text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                                Editar Diseño
                            </a>
                            <a href="formularios_config.php?id=${form.id}" class="block px-4 py-3 text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                                Configurar Notificaciones
                            </a>
                            <button onclick="duplicateForm(${form.id})" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                                Duplicar
                            </button>
                            <button onclick="deleteForm(${form.id}, '${form.titulo.replace(/'/g, "\\'")}')" class="w-full text-left px-4 py-3 text-xs font-bold text-red-500 hover:bg-red-50 transition-colors">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors">${form.titulo}</h3>
                <p class="text-xs text-gray-400 line-clamp-2 min-h-[2.5em]">${form.descripcion || 'Sin descripción'}</p>
                
                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        ${form.entries_count || 0} Entradas
                    </div>
                    <span class="text-[10px] text-gray-300 font-medium">${new Date(form.created_at).toLocaleDateString()}</span>
                </div>
            </div>
            
            <!-- Actions Footer -->
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button onclick="shareForm(${form.id}, '${form.hash_publico}', '${form.titulo.replace(/'/g, "\\'")}')" class="flex-1 flex items-center justify-center gap-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold py-2 rounded-lg hover:bg-gray-50 hover:text-indigo-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                    Compartir
                </button>
                <a href="formularios_entradas.php?id=${form.id}" class="flex-1 flex items-center justify-center gap-2 bg-indigo-600 border border-transparent text-white text-xs font-bold py-2 rounded-lg hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Entradas
                </a>
            </div>
        `;
        grid.appendChild(div);
    });
}

document.addEventListener('DOMContentLoaded', loadForms);

document.addEventListener('click', () => {
    if (activeMenu) {
        activeMenu.classList.add('hidden');
        activeMenu = null;
    }
});

const creationForm = document.getElementById('create-form');
if(creationForm) {
    creationForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = {
            titulo: creationForm.titulo.value,
            descripcion: creationForm.descripcion.value
        };
        
        fetch('api_forms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                closeCreateModal();
                loadForms();
                window.location.href = `formularios_builder.php?id=${res.id}`;
            } else {
                alert('Error: ' + (res.error || 'Unknown'));
            }
        });
    });
}
</script>



<?php require_once 'includes/footer.php'; ?>
