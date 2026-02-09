<?php
require_once 'db.php';

// Procesar Acciones Productos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de validación de seguridad (CSRF).");
    }

    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear_producto':
                $imagen_path = null;
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                    $nombre_archivo = uniqid('prod_') . '.' . $ext;
                    $target_dir = getCompanyUploadPath('productos', true);
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_dir . $nombre_archivo)) {
                        $imagen_path = getCompanyUploadPath('productos') . $nombre_archivo;
                    }
                }
                
                // NOTA: Podríamos agregar haAlcanzadoLimite('productos') si el plan lo requiere
                
                $stmt = $pdo->prepare("INSERT INTO productos (empresa_id, nombre, descripcion, precio_base, unidad_id, impuesto_id, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    getEmpresaId(), 
                    $_POST['nombre'], 
                    $_POST['descripcion'], 
                    $_POST['precio'], 
                    $_POST['unidad_id'] ?: null, 
                    $_POST['impuesto_id'] ?: null,
                    $imagen_path
                ]);
                break;
            case 'editar_producto':
                $imagen_path = $_POST['imagen_actual'] ?? null;
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                    $nombre_archivo = uniqid('prod_') . '.' . $ext;
                    $target_dir = getCompanyUploadPath('productos', true);
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_dir . $nombre_archivo)) {
                        $imagen_path = getCompanyUploadPath('productos') . $nombre_archivo;
                    }
                }
                $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio_base = ?, unidad_id = ?, impuesto_id = ?, imagen = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([
                    $_POST['nombre'], 
                    $_POST['descripcion'], 
                    $_POST['precio'], 
                    $_POST['unidad_id'] ?: null, 
                    $_POST['impuesto_id'] ?: null,
                    $imagen_path,
                    $_POST['id'],
                    getEmpresaId()
                ]);
                break;
            case 'eliminar_producto':
                $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
        }
        header("Location: productos.php");
        exit;
    }
}

// Obtener Datos
$stmt_prod = $pdo->prepare("
    SELECT p.*, u.nombre_unidad, i.nombre_impuesto, i.porcentaje 
    FROM productos p 
    LEFT JOIN unidades_medida u ON p.unidad_id = u.id 
    LEFT JOIN impuestos i ON p.impuesto_id = i.id 
    WHERE p.empresa_id = ?
    ORDER BY p.id DESC
");
$stmt_prod->execute([getEmpresaId()]);
$productos = $stmt_prod->fetchAll();

$stmt_uni = $pdo->prepare("SELECT * FROM unidades_medida WHERE empresa_id = ?");
$stmt_uni->execute([getEmpresaId()]);
$unidades = $stmt_uni->fetchAll();

$stmt_imp = $pdo->prepare("SELECT * FROM impuestos WHERE empresa_id = ?");
$stmt_imp->execute([getEmpresaId()]);
$impuestos = $stmt_imp->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8">
    <!-- Cabecera y Botón Nuevo -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Productos / Servicios</h1>
            <p class="text-gray-500 mt-1">Administra el catálogo de servicios y bienes de tu empresa.</p>
        </div>
        <button onclick="abrirModalProducto()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nuevo Producto
        </button>
    </div>

    <!-- Lista de Productos -->
    <div class="bg-white rounded-3xl border border-gray-100 card-shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Producto</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Precio</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Unidad</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Impuesto</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">No hay productos registrados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($productos as $prod): ?>
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if($prod['imagen']): ?>
                                        <img src="<?php echo getSecureUrl($prod['imagen']); ?>" class="w-10 h-10 rounded-lg object-cover border border-gray-100">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                        <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($prod['descripcion']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900">
                                $<?php echo number_format($prod['precio_base'], 2); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold rounded-md uppercase">
                                    <?php echo $prod['nombre_unidad'] ?: 'N/A'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded-md uppercase">
                                    <?php echo $prod['nombre_impuesto'] ? ($prod['nombre_impuesto'].' ('.$prod['porcentaje'].'%)') : 'Sin Imp.'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editarProducto(<?php echo json_encode($prod); ?>)' class="p-2 text-gray-300 hover:text-blue-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <form action="productos.php" method="POST" onsubmit="return confirm('¿Eliminar este producto?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="accion" value="eliminar_producto">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" class="p-2 text-gray-300 hover:text-red-600 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Producto -->
<div id="modalProducto" class="fixed inset-0 z-[60] hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300">
        <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-xl font-bold text-gray-900" id="tituloModal">Crear Nuevo Producto</h3>
            <button onclick="document.getElementById('modalProducto').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form action="productos.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion" id="inputAccion" value="crear_producto">
            <input type="hidden" name="id" id="inputId" value="">
            
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Nombre del Producto</label>
                <input type="text" name="nombre" id="inputNombre" required placeholder="Ej: Consultoría Básica" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Descripción</label>
                <textarea name="descripcion" id="inputDescripcion" rows="2" placeholder="Breve detalle..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Precio Base</label>
                    <input type="number" step="0.01" name="precio" id="inputPrecio" required placeholder="0.00" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Unidad</label>
                    <select name="unidad_id" id="inputUnidad" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none bg-white transition-all text-sm">
                        <option value="">Ninguna</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nombre_unidad']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Impuesto Aplicable</label>
                <select name="impuesto_id" id="inputImpuesto" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none bg-white transition-all text-sm">
                    <option value="">Ninguno</option>
                    <?php foreach ($impuestos as $i): ?>
                        <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['nombre_impuesto']); ?> (<?php echo $i['porcentaje']; ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Imagen del Producto (Opcional)</label>
                <div class="flex items-center gap-4">
                    <div id="preview-container" class="w-16 h-16 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
                        <img id="img-preview" src="" class="hidden w-full h-full object-cover">
                        <svg id="img-placeholder" class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <input type="file" name="imagen" accept="image/*" onchange="previewImage(this)" class="text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <input type="hidden" name="imagen_actual" id="inputImagenActual">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg transition-all active:scale-95 mt-4">
                Guardar Producto
            </button>
        </form>
    </div>
</div>

<script>
function abrirModalProducto() {
    document.getElementById('inputAccion').value = 'crear_producto';
    document.getElementById('inputId').value = '';
    document.getElementById('inputNombre').value = '';
    document.getElementById('inputDescripcion').value = '';
    document.getElementById('inputPrecio').value = '';
    document.getElementById('inputUnidad').value = '';
    document.getElementById('inputImpuesto').value = '';
    document.getElementById('inputImagenActual').value = '';
    document.getElementById('img-preview').classList.add('hidden');
    document.getElementById('img-placeholder').classList.remove('hidden');
    document.getElementById('tituloModal').innerText = 'Crear Nuevo Producto';
    document.getElementById('modalProducto').classList.remove('hidden');
}

function editarProducto(p) {
    document.getElementById('inputAccion').value = 'editar_producto';
    document.getElementById('inputId').value = p.id;
    document.getElementById('inputNombre').value = p.nombre;
    document.getElementById('inputDescripcion').value = p.descripcion;
    document.getElementById('inputPrecio').value = p.precio_base;
    document.getElementById('inputUnidad').value = p.unidad_id || '';
    document.getElementById('inputImpuesto').value = p.impuesto_id || '';
    document.getElementById('inputImagenActual').value = p.imagen || '';
    if(p.imagen) {
        document.getElementById('img-preview').src = 'f.php?path=' + encodeURIComponent(p.imagen);
        document.getElementById('img-preview').classList.remove('hidden');
        document.getElementById('img-placeholder').classList.add('hidden');
    } else {
        document.getElementById('img-preview').classList.add('hidden');
        document.getElementById('img-placeholder').classList.remove('hidden');
    }
    document.getElementById('tituloModal').innerText = 'Editar Producto';
    document.getElementById('modalProducto').classList.remove('hidden');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('img-preview').src = e.target.result;
            document.getElementById('img-preview').classList.remove('hidden');
            document.getElementById('img-placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
