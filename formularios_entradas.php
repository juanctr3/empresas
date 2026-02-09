<?php
require_once 'includes/auth_helper.php';
require_once 'includes/header.php';
$id = $_GET['id'] ?? 0;
?>

<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="formularios.php" class="p-2 bg-white border border-gray-200 rounded-xl text-gray-400 hover:text-indigo-600 transition-colors shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 id="form-title" class="text-2xl font-black text-gray-800">Entradas del Formulario</h1>
                <p class="text-sm text-gray-500 font-medium">Visualiza y gestiona las respuestas recibidas.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportToCSV()" class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-5 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition-all shadow-sm">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-5-4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Exportar CSV
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Entradas</p>
            <h4 id="stat-total" class="text-2xl font-black text-gray-900">0</h4>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Hoy</p>
            <h4 id="stat-today" class="text-2xl font-black text-indigo-600">0</h4>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-xl shadow-gray-200/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr id="table-headers" class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha</th>
                        <!-- Dynamic headers will go here -->
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="entries-body" class="divide-y divide-gray-50">
                    <!-- Loader -->
                    <tr>
                        <td colspan="100" class="py-20">
                            <div class="flex flex-col items-center gap-4">
                                <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-sm font-bold text-gray-400">Cargando entradas...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDetailModal()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl animate-in slide-in-from-right duration-300 pointer-events-auto">
        <div class="h-full flex flex-col">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Detalle de Entrada</h3>
                <button onclick="closeDetailModal()" class="p-2 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="detail-content" class="flex-1 overflow-y-auto p-8 space-y-8">
                <!-- Content injected here -->
            </div>
        </div>
    </div>
</div>

<script>
    let entriesData = [];
    let fieldsData = [];
    const formId = <?php echo $id; ?>;

    document.addEventListener('DOMContentLoaded', loadEntries);

    async function loadEntries() {
        try {
            const res = await fetch(`api_forms.php?action=get_entries&id=${formId}`);
            const json = await res.json();
            
            if(json.status === 'success') {
                if (!json.data || Array.isArray(json.data) || !json.data.fields) {
                    console.error('API return structure is invalid:', json.data);
                    document.getElementById('entries-body').innerHTML = `<tr><td colspan="100" class="py-20 text-center text-amber-500 font-bold">Error: El servidor devolvió una lista de formularios en lugar de las entradas. Revisa la configuración de la API.</td></tr>`;
                    return;
                }
                entriesData = json.data.entries;
                fieldsData = json.data.fields;
                document.getElementById('form-title').innerText = json.data.form.titulo;
                renderTable();
                updateStats();
            } else {
                alert('Error: ' + json.error);
            }
        } catch (err) {
            console.error('Error loading entries:', err);
            document.getElementById('entries-body').innerHTML = `<tr><td colspan="100" class="py-20 text-center text-red-500 font-bold">Error de conexión: ${err.message}</td></tr>`;
        }
    }

    function updateStats() {
        document.getElementById('stat-total').innerText = entriesData.length;
        const today = new Date().toISOString().split('T')[0];
        const todayCount = entriesData.filter(e => e.created_at.startsWith(today)).length;
        document.getElementById('stat-today').innerText = todayCount;
    }

    function getFieldValue(data, field, idx) {
        if (!data || typeof data !== 'object') return 'No proporcionado';
        
        // 1. Direct match by name (stable ID)
        if (data[field.name] !== undefined && data[field.name] !== null) return data[field.name];
        
        // 2. Match by label
        if (data[field.label] !== undefined && data[field.label] !== null) return data[field.label];
        
        // 3. Match by DB ID (legacy field_ID)
        if (data['field_' + field.id] !== undefined && data['field_' + field.id] !== null) return data['field_' + field.id];

        // 4. Match by order index (legacy field_0)
        if (data['field_' + idx] !== undefined && data['field_' + idx] !== null) return data['field_' + idx];
        
        // 5. Positional fallback
        const keys = Object.keys(data).filter(k => !k.startsWith('_') && k !== 'action' && k !== 'hash');
        // We look for a key that might look like field_X if it matches the index
        if (keys[idx] !== undefined && data[keys[idx]] !== undefined) return data[keys[idx]];
        
        return 'No proporcionado';
    }

    function renderTable() {
        const headers = document.getElementById('table-headers');
        const body = document.getElementById('entries-body');
        
        // Render Headers (Show first 3 fields for summary)
        const summaryFields = fieldsData.filter(f => !['title', 'image', 'html', 'section', 'step', 'header'].includes(f.type)).slice(0, 3);
        
        headers.innerHTML = `
            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">ID</th>
            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha</th>
            ${summaryFields.map(f => `
                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">${f.label}</th>
            `).join('')}
            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 text-right">Acciones</th>
        `;

        if(!entriesData || !Array.isArray(entriesData) || entriesData.length === 0) {
            body.innerHTML = `<tr><td colspan="100" class="py-20 text-center text-gray-400 font-bold">No hay entradas registradas</td></tr>`;
            return;
        }

        body.innerHTML = entriesData.map(entry => {
            let data = {};
            try {
                const rawData = typeof entry.data === 'string' ? JSON.parse(entry.data) : entry.data;
                data = rawData || {};
            } catch(e) {
                data = { _error: 'Invalid JSON' };
            }

            return `
                <tr class="hover:bg-gray-50/50 transition-colors group">
                    <td class="px-6 py-4 text-xs font-black text-gray-400">#${entry.id}</td>
                    <td class="px-6 py-4">
                        <div class="text-xs font-bold text-gray-900">${new Date(entry.created_at).toLocaleDateString()}</div>
                        <div class="text-[10px] text-gray-400 font-medium">${new Date(entry.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    </td>
                    ${summaryFields.map((f, fIdx) => {
                        let val = getFieldValue(data, f, fIdx);

                        if(typeof val === 'object' && val !== null) val = 'Ver detalle';
                        return `<td class="px-6 py-4 text-xs font-bold text-gray-600 truncate max-w-[150px]">${val}</td>`;
                    }).join('')}
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="viewEntry(${entry.id})" class="p-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                            <button onclick="deleteEntry(${entry.id})" class="p-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function viewEntry(id) {
        const entry = entriesData.find(e => e.id === id);
        let data = {};
        try {
            if (typeof entry.data === 'object' && entry.data !== null) {
                data = entry.data;
            } else {
                data = JSON.parse(entry.data || '{}');
            }
        } catch(e) {
            console.error('Error al leer datos de la entrada:', e);
            data = { error: 'Error al leer datos' };
        }

        const content = document.getElementById('detail-content');
        
        content.innerHTML = `
            <div class="bg-gray-50 rounded-2xl p-4 flex justify-between items-center text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                <span>ID: #${entry.id}</span>
                <span>${new Date(entry.created_at).toLocaleString()}</span>
            </div>
            <div class="space-y-6">
                ${fieldsData.map((f, fIdx) => {
                    if(['title', 'html', 'section', 'step', 'header'].includes(f.type)) return '';
                    
                    let val = getFieldValue(data, f, fIdx);

                    let display = '';

                    if(!val) display = '<span class="text-gray-300 italic">No proporcionado</span>';
                    else if(f.type === 'signature') display = `<img src="${val}" class="max-w-full border border-gray-100 rounded-xl bg-white p-2">`;
                    else if(f.type === 'file') {
                        const fileName = val.split('/').pop().split('_').slice(2).join('_') || 'Archivo adjunto';
                        display = `<a href="${val}" target="_blank" class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl font-bold hover:bg-indigo-100 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-5-4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                            ${fileName}
                        </a>`;
                    }
                    else if(f.type === 'product') {
                        const prods = typeof val === 'string' ? JSON.parse(val) : val;
                        display = `<div class="flex flex-wrap gap-2">${prods.map(p => `
                            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-3 flex flex-col items-center gap-1 min-w-[100px]">
                                <span class="bg-indigo-600 text-white px-2 py-0.5 rounded-full text-[8px] font-black uppercase">Prod</span>
                                <span class="text-xs font-black text-gray-800">${p.name}</span>
                                ${p.price ? `<span class="text-[9px] text-indigo-500 font-bold">$${p.price}</span>` : ''}
                            </div>
                        `).join('')}</div>`;
                    }
                    else if(Array.isArray(val)) display = val.join(', ');
                    else display = val;

                    return `
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">${f.label}</label>
                            <div class="text-sm font-bold text-gray-800">${display}</div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div class="pt-8 border-t border-gray-100 mt-10">
                <div class="text-[10px] font-black text-gray-300 uppercase tracking-widest mb-4">Información Técnica</div>
                <div class="grid grid-cols-2 gap-4">
                    <div><p class="text-[9px] text-gray-400 uppercase font-black">Dirección IP</p><p class="text-xs font-bold text-gray-600">${entry.ip_address}</p></div>
                </div>
            </div>
        `;

        document.getElementById('detail-modal').classList.remove('hidden');
    }

    function closeDetailModal() {
        document.getElementById('detail-modal').classList.add('hidden');
    }

    async function deleteEntry(id) {
        if(!confirm('¿Estás seguro de eliminar esta entrada?')) return;
        
        try {
            const res = await fetch(`api_forms.php?action=delete_entry&id=${id}`, { method: 'DELETE' });
            const json = await res.json();
            if(json.status === 'success') {
                entriesData = entriesData.filter(e => e.id !== id);
                renderTable();
                updateStats();
            }
        } catch (err) { alert('Error al eliminar'); }
    }

    function exportToCSV() {
        if(entriesData.length === 0) return alert('No hay datos para exportar');
        
        const headers = ['ID', 'Fecha', ...fieldsData.map(f => f.label), 'IP'];
        const rows = entriesData.map(entry => {
            const data = JSON.parse(entry.data);
            return [
                entry.id,
                entry.created_at,
                ...fieldsData.map(f => {
                    let val = data[f.name] || '';
                    if(typeof val === 'object') return JSON.stringify(val);
                    return val;
                }),
                entry.ip_address
            ];
        });

        let csvContent = "\uFEFF"; // BOM for Excel UTF-8
        csvContent += headers.join(",") + "\n";
        rows.forEach(row => {
            const formattedRow = row.map(val => `"${String(val).replace(/"/g, '""')}"`);
            csvContent += formattedRow.join(",") + "\n";
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `entradas_formulario_${formId}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php require_once 'includes/footer.php'; ?>
