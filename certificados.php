<?php
/**
 * Gestor de Certificados y Garantías - CoticeFacil.com
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';
include 'includes/header.php';

$empresa_id = getEmpresaId();

// Obtener clientes para el modal
$stmt_cl = $pdo->prepare("SELECT id, nombre FROM clientes WHERE empresa_id = ? ORDER BY nombre ASC");
$stmt_cl->execute([$empresa_id]);
$clientes = $stmt_cl->fetchAll();
?>

<div class="space-y-8 animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tighter">Certificados & Garantías</h1>
            <p class="text-gray-500 mt-1">Control de vigencia técnica y garantías extendidas.</p>
        </div>
        <button onclick="openCerModal()" class="bg-gray-900 hover:bg-indigo-600 text-white font-black px-6 py-3 rounded-2xl shadow-xl transition-all active:scale-95 text-xs uppercase tracking-widest flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nuevo Registro
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="certificados-grid">
        <!-- AJAX -->
    </div>
</div>

<!-- Modal -->
<div id="cerModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-[2.5rem] max-w-md w-full shadow-2xl space-y-6">
        <h3 class="text-2xl font-black text-gray-900 tracking-tighter">Nuevo Certificado</h3>
        <form id="cerForm" class="space-y-4">
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 block">Cliente</label>
                <select name="cliente_id" required class="w-full px-4 py-3 rounded-2xl bg-gray-50 border-none outline-none focus:ring-2 focus:ring-indigo-600 text-sm">
                    <?php foreach($clientes as $cl): ?>
                        <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="text" name="nombre_certificado" placeholder="Título (Ej: Garantía HVAC, ISO 9001)" required class="w-full px-4 py-3 rounded-2xl bg-gray-50 border-none outline-none focus:ring-2 focus:ring-indigo-600 text-sm">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 block">Emisión</label>
                    <input type="date" name="fecha_emision" class="w-full px-4 py-3 rounded-2xl bg-gray-50 border-none outline-none focus:ring-2 focus:ring-indigo-600 text-sm">
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 block">Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" class="w-full px-4 py-3 rounded-2xl bg-gray-50 border-none outline-none focus:ring-2 focus:ring-indigo-600 text-sm">
                </div>
            </div>
            <input type="file" name="archivo" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeCerModal()" class="flex-1 py-4 bg-gray-100 text-gray-500 rounded-2xl font-black text-[10px] uppercase tracking-widest">Cancelar</button>
                <button type="submit" class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-indigo-100">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
async function loadCertificados() {
    const res = await fetch('api_certificados.php');
    const data = await res.json();
    if(data.status === 'success') {
        const grid = document.getElementById('certificados-grid');
        if(data.data.length === 0) {
            grid.innerHTML = '<div class="col-span-full p-20 text-center opacity-40 italic">No hay certificados registrados aún.</div>';
            return;
        }
        grid.innerHTML = data.data.map(c => {
            const isExpired = c.fecha_vencimiento && new Date(c.fecha_vencimiento) < new Date();
            return `
                <div class="bg-white p-6 rounded-[2.5rem] border border-gray-100 card-shadow hover:border-indigo-200 transition-all group relative overflow-hidden">
                    ${isExpired ? '<div class="absolute top-4 right-4 bg-red-500 text-white px-2 py-0.5 rounded-lg text-[8px] font-black uppercase tracking-tighter">Vencido</div>' : ''}
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    </div>
                    <h4 class="text-sm font-black text-gray-900 mb-1">${c.nombre_certificado}</h4>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">${c.cliente_nombre}</p>
                    <div class="mt-4 grid grid-cols-2 gap-4 text-[9px] font-black uppercase tracking-tighter">
                        <div>
                            <p class="text-gray-400">Emisión</p>
                            <p class="text-gray-900">${new Date(c.fecha_emision).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <p class="${isExpired ? 'text-red-400' : 'text-gray-400'}">Vencimiento</p>
                            <p class="${isExpired ? 'text-red-600' : 'text-gray-900'}">${c.fecha_vencimiento ? new Date(c.fecha_vencimiento).toLocaleDateString() : 'N/A'}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-2">
                        <a href="${c.url_archivo || '#'}" target="_blank" class="flex-1 py-2.5 bg-gray-900 text-white rounded-xl text-[9px] font-black uppercase tracking-widest text-center hover:bg-indigo-600 transition-colors ${!c.url_archivo ? 'pointer-events-none opacity-20' : ''}">Descargar</a>
                        <button onclick="deleteCer(${c.id})" class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:bg-red-50 hover:text-red-500 border border-transparent hover:border-red-100 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                </div>
            `;
        }).join('');
    }
}

async function deleteCer(id) {
    if(!confirm("¿Deseas eliminar este registro?")) return;
    const res = await fetch('api_certificados.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=eliminar&id=${id}`
    });
    if(res.ok) loadCertificados();
}

function openCerModal() { document.getElementById('cerModal').classList.remove('hidden'); }
function closeCerModal() { document.getElementById('cerModal').classList.add('hidden'); }

document.getElementById('cerForm').onsubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'crear');
    const res = await fetch('api_certificados.php', { method: 'POST', body: formData });
    if(res.ok) {
        closeCerModal();
        loadCertificados();
    }
};

loadCertificados();
</script>

<style>
.card-shadow { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.01); }
</style>

<?php include 'includes/footer.php'; ?>
