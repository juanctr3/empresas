<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Acciones CRUD para Planes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $stmt = $pdo->prepare("INSERT INTO planes (nombre, descripcion, precio, limite_cotizaciones, limite_usuarios) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $_POST['precio'], $_POST['limite_cotizaciones'], $_POST['limite_usuarios']]);
    } elseif ($accion === 'editar') {
        $stmt = $pdo->prepare("UPDATE planes SET nombre = ?, descripcion = ?, precio = ?, limite_cotizaciones = ?, limite_usuarios = ? WHERE id = ?");
        $stmt->execute([$_POST['nombre'], $_POST['descripcion'], $_POST['precio'], $_POST['limite_cotizaciones'], $_POST['limite_usuarios'], $_POST['id']]);
    } elseif ($accion === 'eliminar') {
        $stmt = $pdo->prepare("DELETE FROM planes WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    
    header("Location: saas_planes.php");
    exit;
}

$planes = $pdo->query("SELECT * FROM planes ORDER BY precio ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Planes de Suscripción</h1>
            <p class="text-gray-500 font-medium">Define los niveles de servicio y sus límites.</p>
        </div>
        <button onclick="abrirModalPlan()" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100">
            Nuevo Plan
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($planes as $p): ?>
            <div class="glass-card p-8 flex flex-col group hover:border-indigo-500 transition-all duration-500">
                <div class="flex justify-between items-start mb-4">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase rounded-full tracking-widest">ID #<?php echo $p['id']; ?></span>
                    <div class="flex gap-2">
                        <button onclick='editarPlan(<?php echo json_encode($p); ?>)' class="text-gray-300 hover:text-indigo-600 px-2 py-1 bg-gray-50 rounded-lg transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                    </div>
                </div>
                
                <h3 class="text-2xl font-black text-gray-900 mb-1"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                <p class="text-gray-400 text-sm mb-6 flex-1"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                
                <div class="space-y-3 mb-8">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Precio</span>
                        <span class="font-black text-gray-900">$<?php echo number_format($p['precio'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Límite Cotizaciones</span>
                        <span class="font-black text-indigo-600"><?php echo $p['limite_cotizaciones'] ?: 'Ilimitado'; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Máximo Usuarios</span>
                        <span class="font-black text-indigo-600"><?php echo $p['limite_usuarios'] ?: 'Ilimitado'; ?></span>
                    </div>
                </div>

                <form action="saas_planes.php" method="POST" onsubmit="return confirm('¿Eliminar este plan?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="w-full py-3 bg-gray-50 text-gray-400 font-bold text-[10px] uppercase tracking-widest rounded-xl hover:bg-red-50 hover:text-red-500 transition-all">Eliminar Plan</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Plan -->
<div id="modalPlan" class="fixed inset-0 z-50 hidden bg-black/20 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-lg p-8 shadow-2xl overflow-y-auto max-h-[90vh]">
        <h3 id="modalTitulo" class="text-2xl font-black text-gray-900 mb-6">Configurar Plan</h3>
        <form action="saas_planes.php" method="POST" class="space-y-4">
            <input type="hidden" name="accion" id="inputAccion" value="crear">
            <input type="hidden" name="id" id="inputId">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1 col-span-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre del Plan</label>
                    <input type="text" name="nombre" id="inputNombre" required class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
                </div>
                <div class="space-y-1 col-span-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Descripción Corta</label>
                    <input type="text" name="descripcion" id="inputDescripcion" class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Precio Mensual ($)</label>
                    <input type="number" step="0.01" name="precio" id="inputPrecio" required class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Límite Cotiz. (0=Ilim)</label>
                    <input type="number" name="limite_cotizaciones" id="inputLimiteCot" required class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Límite Usuarios (0=Ilim)</label>
                    <input type="number" name="limite_usuarios" id="inputLimiteUsu" required class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('modalPlan').classList.add('hidden')" class="flex-1 px-4 py-3 rounded-xl border border-gray-100 font-bold text-gray-400 text-[10px] uppercase tracking-widest hover:bg-gray-50 transition-colors">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 font-bold text-white text-[10px] uppercase tracking-widest hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all">Guardar Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalPlan() {
    document.getElementById('inputAccion').value = 'crear';
    document.getElementById('inputId').value = '';
    document.getElementById('inputNombre').value = '';
    document.getElementById('inputPrecio').value = '0.00';
    document.getElementById('inputLimiteCot').value = '0';
    document.getElementById('inputLimiteUsu').value = '0';
    document.getElementById('modalTitulo').innerText = 'Crear Plan';
    document.getElementById('modalPlan').classList.remove('hidden');
}

function editarPlan(p) {
    document.getElementById('inputAccion').value = 'editar';
    document.getElementById('inputId').value = p.id;
    document.getElementById('inputNombre').value = p.nombre;
    document.getElementById('inputDescripcion').value = p.descripcion;
    document.getElementById('inputPrecio').value = p.precio;
    document.getElementById('inputLimiteCot').value = p.limite_cotizaciones;
    document.getElementById('inputLimiteUsu').value = p.limite_usuarios;
    document.getElementById('modalTitulo').innerText = 'Editar Plan';
    document.getElementById('modalPlan').classList.remove('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
