<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    header("Location: login.php");
    exit;
}

// Manejo de Acciones (Subir/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'subir') {
        $nombre = $_POST['nombre'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $file = $_FILES['logo'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "trusted_" . time() . "_" . uniqid() . "." . $ext;
            
            // Usar helper de subida
            $upload_path = getCompanyUploadPath('logos', true);
            $target = $upload_path . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $url = getCompanyUploadPath('logos') . $filename;
                $stmt = $pdo->prepare("INSERT INTO trusted_clients (empresa_id, nombre, logo_url) VALUES (?, ?, ?)");
                $stmt->execute([$empresa_id, $nombre, $url]);
                header("Location: clientes_confianza.php?msg=ok");
                exit;
            }
        }
    }
    
    if ($_POST['accion'] === 'eliminar') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM trusted_clients WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        header("Location: clientes_confianza.php?msg=deleted");
        exit;
    }
}

// Obtener Logos Actuales
$stmt = $pdo->prepare("SELECT * FROM trusted_clients WHERE empresa_id = ? ORDER BY orden ASC");
$stmt->execute([$empresa_id]);
$logos = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-10 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Clientes de Confianza <span class="text-blue-600">✨</span></h1>
            <p class="text-gray-500 font-medium">Gestiona los logos que aparecen en tus propuestas comerciales.</p>
        </div>
        <button onclick="document.getElementById('modalSubir').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 px-8 rounded-2xl shadow-xl shadow-indigo-100 transition-all active:scale-95 flex items-center gap-2 uppercase tracking-widest text-xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Agregar Logo
        </button>
    </div>

    <!-- Grid de Logos -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <?php foreach ($logos as $logo): ?>
            <div class="glass-card p-6 flex flex-col items-center justify-center space-y-4 group relative hover:border-indigo-200 transition-all duration-500">
                <button onclick="confirmarEliminar(<?php echo $logo['id']; ?>)" class="absolute top-2 right-2 p-2 bg-red-50 text-red-500 rounded-lg opacity-0 group-hover:opacity-100 transition-all hover:bg-red-500 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
                <div class="h-16 w-full flex items-center justify-center">
                    <img src="<?php echo htmlspecialchars($logo['logo_url']); ?>" alt="<?php echo htmlspecialchars($logo['nombre']); ?>" class="max-h-full max-w-full object-contain grayscale group-hover:grayscale-0 transition-all duration-500">
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-center truncate w-full"><?php echo htmlspecialchars($logo['nombre']); ?></p>
            </div>
        <?php endforeach; ?>

        <?php if (empty($logos)): ?>
            <div class="col-span-full py-20 text-center border-2 border-dashed border-gray-100 rounded-[2.5rem]">
                <p class="text-gray-400 font-bold italic">No has agregado logos de clientes aún.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Subir -->
<div id="modalSubir" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden animate-slide-up">
        <form method="POST" enctype="multipart/form-data" class="p-8 md:p-12 space-y-6">
            <input type="hidden" name="accion" value="subir">
            <div>
                <h3 class="text-2xl font-black text-gray-900 tracking-tight">Agregar Nuevo Cliente</h3>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">El logo aparecerá en tus propuestas.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Nombre de la Empresa</label>
                    <input type="text" name="nombre" required class="w-full px-5 py-4 rounded-2xl bg-gray-50 border-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm font-bold" placeholder="Ej: Google, Coca Cola...">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Logo (PNG/JPG)</label>
                    <input type="file" name="logo" accept="image/*" required class="w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('modalSubir').classList.add('hidden')" class="flex-1 py-4 px-6 rounded-2xl text-gray-600 font-bold hover:bg-gray-100 transition-all uppercase tracking-widest text-[10px]">Cancelar</button>
                <button type="submit" class="flex-1 py-4 px-6 rounded-2xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-xl active:scale-95 transition-all uppercase tracking-widest text-[10px]">Guardar Logo</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm('¿Seguro que deseas eliminar este logo? Ya no aparecerá en tus propuestas.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
