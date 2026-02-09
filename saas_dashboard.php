<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar que solo un Super Admin pueda ver esto
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Estadísticas de la Plataforma
$total_empresas = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
$total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$total_cotizaciones = $pdo->query("SELECT COUNT(*) FROM cotizaciones")->fetchColumn();

// Ingresos estimados (simulado con precio de planes)
$ingresos = $pdo->query("SELECT SUM(p.precio) FROM empresas e JOIN planes p ON e.plan_id = p.id")->fetchColumn() ?: 0;

// Últimas empresas registradas
$empresas = $pdo->query("
    SELECT e.*, p.nombre as plan_nombre 
    FROM empresas e 
    LEFT JOIN planes p ON e.plan_id = p.id 
    ORDER BY e.created_at DESC 
    LIMIT 10
")->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-10 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-4xl font-black text-gray-900 tracking-tight">Dashboard <span class="text-indigo-600">SaaS</span></h1>
            <p class="text-gray-500 font-medium text-lg">Control total de la infraestructura de CoticeFacil.</p>
        </div>
    </div>

    <!-- Platform Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 p-8 rounded-[2rem] text-white shadow-2xl relative overflow-hidden group">
            <p class="text-white/70 text-[10px] font-black uppercase tracking-widest mb-1">Empresas Activas</p>
            <h3 class="text-3xl font-black tracking-tighter"><?php echo $total_empresas; ?></h3>
        </div>

        <div class="glass-card p-8 border-l-4 border-l-green-500">
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Usuarios Totales</p>
            <h3 class="text-3xl font-black text-gray-900 tracking-tighter"><?php echo $total_usuarios; ?></h3>
        </div>

        <div class="glass-card p-8 border-l-4 border-l-blue-500">
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Cotizaciones Producidas</p>
            <h3 class="text-3xl font-black text-gray-900 tracking-tighter"><?php echo $total_cotizaciones; ?></h3>
        </div>

        <div class="glass-card p-8 border-l-4 border-l-pink-500">
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Facturación Mensual (MRR)</p>
            <h3 class="text-3xl font-black text-gray-900 tracking-tighter">$<?php echo number_format($ingresos, 2); ?></h3>
        </div>
    </div>

    <!-- Company Management Overview -->
    <div class="glass-card p-8">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-xl font-black text-gray-800 tracking-tight">Empresas Recientes</h2>
            <div class="flex gap-2">
                <a href="saas_auditoria.php" class="text-[10px] font-black text-indigo-500 uppercase tracking-widest px-4 py-2 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">Auditoría</a>
                <a href="saas_usuarios.php" class="text-[10px] font-black text-indigo-500 uppercase tracking-widest px-4 py-2 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">Gestionar Usuarios</a>
                <a href="saas_planes.php" class="text-[10px] font-black text-indigo-500 uppercase tracking-widest px-4 py-2 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">Gestionar Planes</a>
                <a href="saas_empresas.php" class="text-[10px] font-black text-white uppercase tracking-widest px-4 py-2 bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-lg">Ver Todas</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="px-4 py-4">Empresa</th>
                        <th class="px-4 py-4">Plan Actual</th>
                        <th class="px-4 py-4">NIT</th>
                        <th class="px-4 py-4">Registro</th>
                        <th class="px-4 py-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($empresas as $emp): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold">
                                        <?php echo substr($emp['nombre'], 0, 1); ?>
                                    </div>
                                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($emp['nombre']); ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 bg-blue-50 text-blue-600 text-[9px] font-black uppercase rounded-full">
                                    <?php echo $emp['plan_nombre']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500"><?php echo $emp['nit']; ?></td>
                            <td class="px-4 py-4 text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($emp['created_at'])); ?></td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="iniciarSoporte(<?php echo $emp['id']; ?>)" class="text-xs font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-widest bg-indigo-50 px-3 py-1.5 rounded-lg transition-all" title="Entrar como esta empresa">Soporte</button>
                                    <button class="text-gray-400 hover:text-indigo-600 p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function iniciarSoporte(id) {
    if (!confirm('¿Deseas iniciar sesión en modo soporte para esta empresa?')) return;
    try {
        const res = await fetch('api_saas_imitate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'start', empresa_id: id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.href = 'index.php';
        } else {
            alert(data.message);
        }
    } catch(e) { console.error(e); }
}
</script>

<?php include 'includes/footer.php'; ?>
