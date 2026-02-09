<?php
require_once 'db.php';
$empresa_id = getEmpresaId();

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $desc = $_POST['descripcion'];
    $tipo = $_POST['tipo'];
    
    if(!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE tareas_empresa SET nombre=?, descripcion=?, tipo=? WHERE id=? AND empresa_id=?");
        $stmt->execute([$nombre, $desc, $tipo, $_POST['id'], $empresa_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO tareas_empresa (empresa_id, nombre, descripcion, tipo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $nombre, $desc, $tipo]);
    }
    header("Location: configurar-tareas.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM tareas_empresa WHERE id=? AND empresa_id=?");
    $stmt->execute([$_GET['delete'], $empresa_id]);
    header("Location: configurar-tareas.php");
    exit;
}

$tareas = $pdo->query("SELECT * FROM tareas_empresa WHERE empresa_id = $empresa_id ORDER BY id DESC")->fetchAll();
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-gray-900">Flujos y Tareas</h1>
            <p class="text-gray-500">Define qué debe suceder cuando un cliente acepta una propuesta.</p>
        </div>
        <button onclick="abrirModal()" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition">
            + Nueva Tarea
        </button>
    </div>

    <div class="grid gap-4">
        <?php foreach($tareas as $t): ?>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex justify-between items-center">
            <div>
                <span class="text-xs font-black uppercase tracking-widest <?php echo $t['tipo'] == 'post_firma' ? 'text-green-600 bg-green-50' : 'text-orange-600 bg-orange-50'; ?> px-2 py-1 rounded-md mb-2 inline-block">
                    <?php echo $t['tipo'] == 'post_firma' ? 'Post-Firma (Aprobada)' : 'Pre-Firma'; ?>
                </span>
                <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($t['nombre']); ?></h3>
                <p class="text-gray-500 text-sm mt-1"><?php echo htmlspecialchars($t['descripcion']); ?></p>
            </div>
            <div class="flex gap-2">
                <button onclick='editar(<?php echo json_encode($t); ?>)' class="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100">Editar</button>
                <a href="?delete=<?php echo $t['id']; ?>" onclick="return confirm('¿Eliminar?')" class="px-4 py-2 text-sm font-bold text-red-600 bg-red-50 rounded-lg hover:bg-red-100">Eliminar</a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($tareas)): ?>
            <div class="text-center py-12 bg-gray-50 rounded-3xl border-2 border-dashed border-gray-200">
                <p class="text-gray-400 font-bold">No tienes tareas definidas.</p>
                <p class="text-sm text-gray-400">Crea una para automatizar tu proceso post-venta.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="modalTarea" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl p-8 shadow-2xl">
        <h3 class="text-2xl font-black text-gray-900 mb-6" id="modalTitle">Nueva Tarea</h3>
        <form method="POST">
            <input type="hidden" name="id" id="inputId">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nombre de la Tarea</label>
                    <input type="text" name="nombre" id="inputNombre" required class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Subir RUT, Llenar Formulario de Entrega...">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Descripción / Instrucciones</label>
                     <textarea name="descripcion" id="inputDesc" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Explica al cliente qué debe hacer..."></textarea>
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase mb-1">¿Cuándo se ejecuta?</label>
                     <select name="tipo" id="inputTipo" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-blue-500">
                         <option value="post_firma">Después de Firmar (Aprobada)</option>
                         <option value="pre_firma">Antes de Firmar (Requisito)</option>
                     </select>
                </div>
            </div>
            <div class="mt-8 flex gap-3">
                <button type="button" onclick="cerrarModal()" class="flex-1 py-3 font-bold text-gray-500 hover:bg-gray-50 rounded-xl">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modalTarea').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Nueva Tarea';
    document.getElementById('inputId').value = '';
    document.getElementById('inputNombre').value = '';
    document.getElementById('inputDesc').value = '';
}
function cerrarModal() {
    document.getElementById('modalTarea').classList.add('hidden');
}
function editar(t) {
    abrirModal();
    document.getElementById('modalTitle').textContent = 'Editar Tarea';
    document.getElementById('inputId').value = t.id;
    document.getElementById('inputNombre').value = t.nombre;
    document.getElementById('inputDesc').value = t.descripcion;
    document.getElementById('inputTipo').value = t.tipo;
}
</script>

<?php include 'includes/footer.php'; ?>
