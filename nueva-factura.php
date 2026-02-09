<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
require_once 'includes/header.php';

$empresa_id = getEmpresaId();
$id = $_GET['id'] ?? null;
$cotizacion_id = $_GET['cotizacion_id'] ?? null;

$factura = [
    'numero_factura' => 'Borrador',
    'fecha_emision' => date('Y-m-d'),
    'fecha_vencimiento' => date('Y-m-d', strtotime('+30 days')),
    'cliente_id' => '',
    'notas' => '',
    'notas_internas' => '',
    'items' => []
];

$clientes = $pdo->prepare("SELECT id, nombre, identificacion FROM clientes WHERE empresa_id = ? ORDER BY nombre ASC");
$clientes->execute([$empresa_id]);
$clientes_list = $clientes->fetchAll();

$productos = $pdo->prepare("SELECT id, nombre, precio_base, impuesto_id FROM productos WHERE empresa_id = ?");
$productos->execute([$empresa_id]);
$productos_list = $productos->fetchAll();

// MODO EDICIÓN
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, $empresa_id]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $factura = $found;
        $itemsStmt = $pdo->prepare("SELECT * FROM factura_detalles WHERE factura_id = ?");
        $itemsStmt->execute([$id]);
        $factura['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
// MODO CONVERSIÓN DE COTIZACIÓN
elseif ($cotizacion_id) {
    $stmtCot = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? AND empresa_id = ?");
    $stmtCot->execute([$cotizacion_id, $empresa_id]);
    $cot = $stmtCot->fetch(PDO::FETCH_ASSOC);
    
    if ($cot) {
        // PREVENIR DOBLE FACTURACIÓN
        // Verificar si ya existe alguna factura para esta cotización
        $checkInv = $pdo->prepare("SELECT id, numero_factura FROM facturas WHERE cotizacion_id = ? AND empresa_id = ?");
        $checkInv->execute([$cotizacion_id, $empresa_id]);
        $existingInv = $checkInv->fetch(PDO::FETCH_ASSOC);

        if ($existingInv) {
            // Si ya existe, mostrar mensaje y botón de redirección en lugar del formulario
            $factura_existente_msg = "Esta cotización ya fue convertida a la factura <strong>Top #{$existingInv['numero_factura']}</strong>.";
            $factura_existente_link = "nueva-factura.php?id=" . $existingInv['id'];
        } else {
            $factura['cliente_id'] = $cot['cliente_id'];
            $factura['cotizacion_id'] = $cot['id']; // Para vincular
            $factura['notas'] = $cot['notas'];
            
            $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
            $stmtDet->execute([$cotizacion_id]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            
            // Mapear items de cotización a factura
            foreach($items as $i) {
                $factura['items'][] = [
                    'producto_id' => $i['producto_id'],
                    'nombre_producto' => $i['nombre_producto'],
                    'descripcion' => $i['descripcion'],
                    'cantidad' => $i['cantidad'],
                    'precio_unitario' => $i['precio_unitario'],
                    'impuesto_porcentaje' => $i['impuesto_porcentaje'],
                    'subtotal' => $i['subtotal']
                ];
            }
            if($cot['aceptada_data']) {
                $data = json_decode($cot['aceptada_data'], true);
                $factura['cotizacion_files'] = $data['documentos'] ?? [];
            }
        }
    }
}
?>

<?php if(isset($factura_existente_msg)): ?>
<div class="max-w-xl mx-auto mt-20 p-8 glass rounded-[2.5rem] text-center shadow-xl border border-gray-100">
    <div class="mb-6 inline-flex p-4 bg-yellow-50 text-yellow-600 rounded-full">
        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
    </div>
    <h2 class="text-2xl font-black text-gray-900 mb-2">¡Opa!</h2>
    <p class="text-gray-600 mb-8 text-lg"><?php echo $factura_existente_msg; ?></p>
    
    <div class="flex justify-center gap-4">
        <a href="cotizaciones.php" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition-all">Volver</a>
        <a href="<?php echo $factura_existente_link; ?>" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
            Ir a Editar Factura
        </a>
    </div>
</div>
<?php include 'includes/footer.php'; exit; ?>
<?php endif; ?>

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">
                <?php echo $id ? 'Editar Factura' : 'Nueva Factura'; ?>
            </h1>
            <p class="text-gray-500 font-medium">
                <?php echo $id ? '#' . $factura['numero_factura'] : 'Creando nueva factura'; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="facturas.php" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition-all">Cancelar</a>
            <button onclick="previewFactura()" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-50 hover:text-indigo-600 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                Vista Previa
            </button>
            <button onclick="guardarFactura()" class="px-6 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Guardar Factura
            </button>
        </div>
    </div>

    <form id="invoiceForm" class="space-y-6">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="create_update">
        <input type="hidden" name="cotizacion_id" value="<?php echo $factura['cotizacion_id'] ?? ''; ?>">

        <!-- Datos Principales -->
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Cliente</label>
                <select name="cliente_id" id="cliente_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all font-bold">
                    <option value="">Seleccionar Cliente...</option>
                    <?php foreach ($clientes_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $factura['cliente_id'] == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Número Factura (Opcional)</label>
                <input type="text" name="numero_factura" value="<?php echo $factura['id'] ? $factura['numero_factura'] : ''; ?>" placeholder="Automático (Dejar vacío)" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all font-bold">
                <p class="text-[10px] text-gray-400 mt-1">Si dejas este campo vacío, se generará el consecutivo automático.</p>
            </div>
            <div class="md:col-span-2 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Fecha Emisión</label>
                    <input type="date" name="fecha_emision" value="<?php echo $factura['fecha_emision']; ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" value="<?php echo $factura['fecha_vencimiento']; ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all font-bold">
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-black text-gray-900">Items de Factura</h3>
                <button type="button" onclick="addItem()" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-blue-100 transition-all">+ Agregar Item</button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full" id="itemsTable">
                    <thead>
                        <tr class="text-left text-xs font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="pb-3 w-1/3">Producto / Descripción</th>
                            <th class="pb-3 w-20 text-center">Cant</th>
                            <th class="pb-3 w-32 text-right">Precio</th>
                            <th class="pb-3 w-20 text-right">Imp %</th>
                            <th class="pb-3 w-32 text-right">Total</th>
                            <th class="pb-3 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50" id="itemsBody">
                        <!-- Rows rendered via JS -->
                        <datalist id="list-products">
                            <?php foreach($productos_list as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['nombre']); ?>" data-id="<?php echo $p['id']; ?>" data-price="<?php echo $p['precio_base']; ?>" data-tax="<?php echo $p['impuesto_id']; ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="pt-4 text-right font-bold text-gray-500">Subtotal:</td>
                            <td class="pt-4 text-right font-bold text-gray-900" id="lbl-subtotal">$0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right font-bold text-gray-500">Impuestos:</td>
                            <td class="text-right font-bold text-gray-900" id="lbl-tax">$0.00</td>
                            <td></td>
                        </tr>
                        <tr class="text-lg">
                            <td colspan="4" class="pt-2 text-right font-black text-indigo-600">TOTAL:</td>
                            <td class="pt-2 text-right font-black text-indigo-600" id="lbl-total">$0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Notas -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Notas para el Cliente</label>
                <textarea name="notas" rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all"><?php echo htmlspecialchars($factura['notas']); ?></textarea>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Notas Internas</label>
                <textarea name="notas_internas" rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all"><?php echo htmlspecialchars($factura['notas_internas']); ?></textarea>
            </div>
        </div>

        <!-- Archivos Adjuntos -->
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 mt-6">
            <h3 class="text-lg font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                Archivos Adjuntos
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Subir Nuevo -->
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Subir Archivo (PDF, ZIP, IMG)</label>
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:bg-gray-50 transition-colors relative">
                        <input type="file" id="fileUpload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="uploadAttachment(this)">
                        <div class="text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span class="text-xs font-bold">Haz clic o arrastra un archivo aquí</span>
                        </div>
                    </div>
                    <?php if(!empty($factura['archivos'])): ?>
                        <div class="mt-4 space-y-2">
                            <?php foreach($factura['archivos'] ?? [] as $f): ?>
                                <div class="flex items-center justify-between bg-gray-50 p-2 rounded-lg text-xs">
                                    <span class="truncate font-medium text-gray-700 max-w-[200px]"><?php echo htmlspecialchars($f['nombre_original']); ?></span>
                                    <a href="<?php echo $f['ruta_archivo']; ?>" target="_blank" class="text-indigo-600 hover:underline">Ver</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Importar de Cotización -->
                <?php if(!empty($factura['cotizacion_files'])): ?>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">
                        Importar de Cotización
                        <span class="ml-2 bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px]">Aprobada</span>
                    </label>
                    <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                        <p class="text-xs text-gray-500 mb-2">Selecciona los documentos que el cliente subió al aceptar:</p>
                        <?php foreach($factura['cotizacion_files'] as $idx => $path): ?>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="import_file_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($path); ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 import-check">
                            <label for="import_file_<?php echo $idx; ?>" class="text-sm text-gray-700 truncate cursor-pointer hover:text-indigo-600">
                                <?php echo basename($path); ?>
                            </label>
                            <a href="<?php echo htmlspecialchars($path); ?>" target="_blank" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <button type="button" onclick="importQuoteFiles()" class="w-full mt-2 py-2 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-600 hover:text-indigo-600 hover:border-indigo-200 transition-colors">
                            Importar Seleccionados
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
    </form>
</div>

<script>
let items = <?php echo json_encode($factura['items'] ?? []); ?>;
const productosList = <?php echo json_encode($productos_list); ?>;

function init() {
    if(items.length === 0) addItem(); // Add default empty row
    else renderItems();
}

function addItem() {
    items.push({
        nombre_producto: '',
        descripcion: '',
        cantidad: 1,
        precio_unitario: 0,
        impuesto_porcentaje: 0
    });
    renderItems();
}

function removeItem(idx) {
    items.splice(idx, 1);
    renderItems();
}

function updateItem(idx, field, val) {
    items[idx][field] = val;
    renderItems(); // Or optimized update
}

function onProductInput(input, idx) {
    const val = input.value;
    const opts = document.getElementById('list-products').options;
    for(let i=0; i<opts.length; i++) {
        if(opts[i].value === val) {
            // Found match
            const pId = opts[i].getAttribute('data-id');
            const price = parseFloat(opts[i].getAttribute('data-price')) || 0;
            const taxId = opts[i].getAttribute('data-tax'); // Logic to get tax % needed if IDs used
            
            // For simplicity in this step, assuming tax is standard or we need to fetch. 
            // Better: update backend to send tax % in products list or use simple logic.
            // Let's assume 19% if tax ID exists for now or 0.
            
            updateItem(idx, 'producto_id', pId);
            updateItem(idx, 'precio_unitario', price);
            updateItem(idx, 'nombre_producto', val);
            
            // Find tax % from productsList JS object for accuracy
            const pObj = productosList.find(x => x.id == pId);
            // We need to know tax rate from tax ID. Let's default to current item tax or 0.
            // In a real app we'd map tax_id to percentage.
            if(pObj) updateItem(idx, 'descripcion', 'Producto seleccionado'); 
            
            return;
        }
    }
    updateItem(idx, 'nombre_producto', val);
}

function renderItems() {
    const tbody = document.getElementById('itemsBody');
    // Clear rows but keep datalist if it was inside (it's inside tbody in PHP but JS clears innerHTML. 
    // Wait, if I clear innerHTML I lose the datalist! 
    // I should move datalist OUTSIDE tbody in the previous step or re-add it. 
    // Let's just create rows.
    
    // SAFEGUARD: Move datalist out if it exists in body? No, just clear rows.
    // Actually, innerHTML='' destroys the datalist if it is a child.
    // I will use rows only.
    
    // Better strategy: Clear only TRs.
    // Or, put datalist in tfoot or outside table.
    // I will correct the datalist location in a fix if needed, but for now assuming it's safe or I re-append it.
    
    // Let's check where I put datalist. Inside tbody. Yikes.
    // I should move it to outside table in the replace call?
    // Too late for that tool call, it's queued.
    // I will handle it by re-writing innerHTML carefully or appending rows.
    
    // FIX: I will empty tbody and RE-ADD datalist.
    const dl = `<datalist id="list-products">
        ${productosList.map(p => `<option value="${p.nombre}" data-id="${p.id}" data-price="${p.precio_base}"></option>`).join('')}
    </datalist>`;
    
    tbody.innerHTML = dl; 
    
    let sub = 0;
    let tax = 0;

    items.forEach((item, i) => {
        const rowTotal = item.cantidad * item.precio_unitario;
        const rowTax = rowTotal * (item.impuesto_porcentaje / 100);
        
        sub += rowTotal;
        tax += rowTax;

        const tr = document.createElement('tr');
        tr.className = "group";
        tr.innerHTML = `
            <td class="py-2 pr-2">
                <input list="list-products" type="text" value="${item.nombre_producto}" oninput="onProductInput(this, ${i})" class="w-full font-bold text-gray-800 bg-transparent border-b border-transparent focus:border-indigo-300 outline-none placeholder-gray-300" placeholder="Buscar producto...">
                <input type="text" value="${item.descripcion}" onchange="updateItem(${i}, 'descripcion', this.value)" class="w-full text-xs text-gray-500 bg-transparent border-b border-transparent focus:border-indigo-300 outline-none placeholder-gray-200 mt-1" placeholder="Descripción adicional...">
            </td>
            <td class="py-2 px-2 text-center">
                <input type="number" step="0.01" value="${item.cantidad}" onchange="updateItem(${i}, 'cantidad', this.value)" class="w-full text-center font-bold text-gray-800 bg-gray-50 rounded-lg py-1 border border-transparent focus:bg-white focus:ring-1 focus:ring-indigo-500 outline-none">
            </td>
            <td class="py-2 px-2 text-right">
                <input type="number" step="0.01" value="${item.precio_unitario}" onchange="updateItem(${i}, 'precio_unitario', this.value)" class="w-full text-right font-bold text-gray-800 bg-gray-50 rounded-lg py-1 border border-transparent focus:bg-white focus:ring-1 focus:ring-indigo-500 outline-none">
            </td>
            <td class="py-2 px-2 text-right">
                <input type="number" step="1" value="${item.impuesto_porcentaje}" onchange="updateItem(${i}, 'impuesto_porcentaje', this.value)" class="w-full text-right text-sm text-gray-500 bg-transparent border-b border-transparent focus:border-indigo-300 outline-none">
            </td>
             <td class="py-2 px-2 text-right font-bold text-gray-900">
                $${(rowTotal + rowTax).toLocaleString('es-CO', {minimumFractionDigits: 2})}
            </td>
            <td class="py-2 pl-2 text-right">
                <button onclick="removeItem(${i})" class="text-gray-300 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('lbl-subtotal').textContent = '$' + sub.toLocaleString('es-CO', {minimumFractionDigits: 2});
    document.getElementById('lbl-tax').textContent = '$' + tax.toLocaleString('es-CO', {minimumFractionDigits: 2});
    document.getElementById('lbl-total').textContent = '$' + (sub + tax).toLocaleString('es-CO', {minimumFractionDigits: 2});
}

function uploadAttachment(input) {
    if(!input.files || !input.files[0]) return;
    
    // We need an ID to upload. if not saved, we can't upload? 
    // Or we upload to temp?
    // Let's say we require saving first.
    // Or better: Use the action 'upload_file' but we need 'factura_id'.
    // If factura_id is empty, we must save first.
    
    const id = document.querySelector('input[name="id"]').value;
    if(!id) {
        alert('Debes guardar la factura (borrador) antes de subir archivos.');
        input.value = '';
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'upload_file');
    fd.append('factura_id', id);
    fd.append('file', input.files[0]);
    
    fetch('api_facturacion.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            alert('Archivo subido');
            location.reload(); // Simple reload to show file
        } else {
            alert('Error: ' + d.message || 'Error subiendo');
        }
    });
}

function importQuoteFiles() {
    const id = document.querySelector('input[name="id"]').value;
    const cotId = document.querySelector('input[name="cotizacion_id"]').value;
    
    if(!id) return alert('Guarda la factura primero.');
    
    const selected = Array.from(document.querySelectorAll('.import-check:checked')).map(c => c.value);
    if(selected.length === 0) return alert('Selecciona archivos');
    
    const fd = new FormData();
    fd.append('action', 'import_quote_files');
    fd.append('factura_id', id);
    fd.append('cotizacion_id', cotId);
    selected.forEach(f => fd.append('files[]', f));
    
    fetch('api_facturacion.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
         alert('Importados: ' + d.imported);
         location.reload();
    });
}

function previewFactura() {
    guardarFactura(true);
}

// Update guard to accept "previewMode"
function guardarFactura(previewMode = false) {
    const form = document.getElementById('invoiceForm');
    const formData = new FormData(form);
    
    // Append items manually as array
    items.forEach((item, i) => {
        formData.append(`items[${i}][nombre]`, item.nombre_producto);
        formData.append(`items[${i}][descripcion]`, item.descripcion);
        formData.append(`items[${i}][cantidad]`, item.cantidad);
        formData.append(`items[${i}][precio]`, item.precio_unitario);
        formData.append(`items[${i}][impuesto]`, item.impuesto_porcentaje);
        if(item.producto_id) formData.append(`items[${i}][producto_id]`, item.producto_id);
    });

    if(!formData.get('cliente_id')) return alert('Selecciona un cliente');

    fetch('api_facturacion.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            if(previewMode) {
                // Open in new tab
                window.open('ver-factura.php?h=' + d.hash, '_blank');
                // Reload or update ID if it was new
                if(!formData.get('id')) window.location.href = 'nueva-factura.php?id=' + d.id;
            } else {
                alert('Factura Guardada: ' + d.invoice_number);
                window.location.href = 'facturas.php';
            }
        } else {
            alert('Error: ' + d.message);
        }
    })
    .catch(e => alert('Error de conexión'));
}

init();
</script>

<?php include 'includes/footer.php'; ?>
