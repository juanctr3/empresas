<?php
/**
 * Dashboard de Alertas y Seguimiento - CoticeFacil.com
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';
include 'includes/header.php';

$empresa_id = getEmpresaId();
?>

<div class="space-y-8 animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tighter">Alertas & Seguimiento</h1>
            <p class="text-gray-500 mt-1">Gestiona los recordatorios y cierres de ventas programados.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Columna Pendientes -->
        <div class="space-y-4">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> Pendientes hoy
            </h3>
            <div id="alertas-pendientes" class="space-y-3">
                <!-- AJAX -->
            </div>
        </div>

        <!-- Columna Próximas -->
        <div class="space-y-4">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 bg-blue-500 rounded-full"></span> Próximos 7 días
            </h3>
            <div id="alertas-proximas" class="space-y-3">
                <!-- AJAX -->
            </div>
        </div>

        <!-- Columna Completadas/Historial -->
        <div class="space-y-4">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full"></span> Resueltas recientemente
            </h3>
            <div id="alertas-resueltas" class="space-y-3">
                <!-- AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
async function loadAlertas() {
    // Reutlizaré api_alertas.php pero necesito una acción 'listar_dashboard'
    const res = await fetch('api_alertas.php?action=listar_dashboard');
    const data = await res.json();
    
    if (data.status === 'success') {
        const p = document.getElementById('alertas-pendientes');
        const f = document.getElementById('alertas-proximas');
        const r = document.getElementById('alertas-resueltas');

        p.innerHTML = renderGroup(data.hoy);
        f.innerHTML = renderGroup(data.proximas);
        r.innerHTML = renderGroup(data.resueltas, true);
    }
}

function renderGroup(items, isResolved = false) {
    if(!items || items.length === 0) return '<div class="p-8 border border-dashed border-gray-100 rounded-3xl text-center text-gray-400 text-[10px] font-black uppercase">Sin registros</div>';
    
    return items.map(a => `
        <div class="bg-white p-5 rounded-[2rem] border border-gray-100 card-shadow hover:border-indigo-100 transition-all ${isResolved ? 'opacity-50' : ''}">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-md">${a.tipo}</span>
                ${!isResolved ? `<button onclick="resolveAlerta(${a.id})" class="w-6 h-6 bg-green-50 text-green-600 rounded-full flex items-center justify-center hover:bg-green-600 hover:text-white transition-all"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg></button>` : ''}
            </div>
            <h4 class="text-sm font-black text-gray-900">${a.titulo}</h4>
            <p class="text-[10px] text-gray-400 line-clamp-2 mt-1 italic">"${a.mensaje}"</p>
            <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-[10px] font-bold text-gray-500">${a.cliente_nombre[0]}</div>
                    <span class="text-[10px] font-bold text-gray-600">${a.cliente_nombre}</span>
                </div>
                <span class="text-[10px] font-black text-gray-400">${new Date(a.fecha_alerta).toLocaleDateString()}</span>
            </div>
        </div>
    `).join('');
}

async function resolveAlerta(id) {
    const res = await fetch('api_alertas.php?action=completar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    });
    if(res.ok) loadAlertas();
}

loadAlertas();
</script>

<style>
.card-shadow { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.01); }
</style>

<?php include 'includes/footer.php'; ?>
