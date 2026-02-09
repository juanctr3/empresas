<?php
require_once 'db.php';

$hash = $_GET['h'] ?? '';
if (!$hash) die("Acceso inválido");

// Obtener Factura por Hash
$stmt = $pdo->prepare("SELECT f.*, e.nombre as empresa_nombre, e.logo as empresa_logo, e.nit as empresa_nit, e.color_hex, c.nombre as cliente_nombre, c.identificacion as cliente_identificacion, c.email as cliente_email, c.direccion as cliente_direccion, c.telefono as cliente_telefono 
FROM facturas f 
JOIN empresas e ON f.empresa_id = e.id 
JOIN clientes c ON f.cliente_id = c.id 
WHERE f.hash_publico = ?");
$stmt->execute([$hash]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$factura) die("Factura no encontrada o enlace caducado.");

// Obtener Items
$stmtDet = $pdo->prepare("SELECT * FROM factura_detalles WHERE factura_id = ?");
$stmtDet->execute([$factura['id']]);
$items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$stmtFiles = $pdo->prepare("SELECT * FROM factura_archivos WHERE factura_id = ?");
$stmtFiles->execute([$factura['id']]);
$archivos = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

$color = $factura['color_hex'] ?: '#4f46e5';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura #<?php echo htmlspecialchars($factura['numero_factura']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .print-break { page-break-inside: avoid; }
        @media print {
            body { background-color: white; }
            .no-print { display: none !important; }
            .shadow-2xl { shadow: none !important; }
            .glass { background: white; }
        }
    </style>
</head>
<body class="py-10 px-4 min-h-screen flex flex-col items-center">

    <!-- Actions Bar -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 no-print flex gap-4 bg-gray-900/80 backdrop-blur-md p-2 rounded-2xl shadow-2xl">
        <button onclick="window.print()" class="px-6 py-3 bg-white text-gray-900 rounded-xl font-bold hover:bg-gray-100 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Imprimir / PDF
        </button>
        <a href="mailto:<?php echo $factura['cliente_email']; ?>?subject=Consulta Factura <?php echo $factura['numero_factura']; ?>" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            Contactar
        </a>
    </div>

    <div class="max-w-4xl w-full glass rounded-[2.5rem] shadow-2xl overflow-hidden relative">
        <!-- Top accent -->
        <div class="h-4 w-full" style="background: <?php echo $color; ?>"></div>

        <div class="p-10 md:p-16">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-16 gap-8">
                <div>
                    <?php if($factura['empresa_logo']): ?>
                        <img src="<?php echo htmlspecialchars($factura['empresa_logo']); ?>" alt="Logo" class="h-20 object-contain mb-4">
                    <?php else: ?>
                        <h2 class="text-3xl font-black text-gray-900 mb-2"><?php echo htmlspecialchars($factura['empresa_nombre']); ?></h2>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 font-medium max-w-xs">
                        NIT: <?php echo htmlspecialchars($factura['empresa_nit'] ?? 'N/A'); ?><br>
                        <?php echo htmlspecialchars($factura['empresa_nombre']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <h1 class="text-5xl font-black text-gray-900 tracking-tighter mb-2">FACTURA</h1>
                    <p class="text-2xl font-bold text-gray-400">#<?php echo htmlspecialchars($factura['numero_factura']); ?></p>
                    <div class="mt-4 inline-block px-4 py-1.5 rounded-lg bg-gray-100 font-bold text-sm uppercase tracking-widest text-gray-600">
                        <?php echo $factura['estado']; ?>
                    </div>
                </div>
            </div>

            <!-- Dates & Client -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-16">
                <div>
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Facturado A:</h3>
                    <p class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($factura['cliente_nombre']); ?></p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        <?php if($factura['cliente_identificacion']) echo "ID: " . $factura['cliente_identificacion'] . "<br>"; ?>
                        <?php if($factura['cliente_direccion']) echo htmlspecialchars($factura['cliente_direccion']) . "<br>"; ?>
                        <?php if($factura['cliente_telefono']) echo htmlspecialchars($factura['cliente_telefono']); ?>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Fecha Emisión</h3>
                        <p class="text-lg font-bold text-gray-900"><?php echo date('d M, Y', strtotime($factura['fecha_emision'])); ?></p>
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Vencimiento</h3>
                        <p class="text-lg font-bold text-gray-900"><?php echo date('d M, Y', strtotime($factura['fecha_vencimiento'])); ?></p>
                    </div>
                    <div class="col-span-2 mt-4 bg-gray-50 p-4 rounded-2xl border border-gray-100">
                        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Monto Total a Pagar</p>
                        <p class="text-3xl font-black text-gray-900" style="color: <?php echo $color; ?>">$<?php echo number_format($factura['total'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="mb-16">
                <table class="w-full">
                    <thead>
                        <tr class="text-left border-b-2 border-gray-100">
                            <th class="pb-4 text-xs font-black text-gray-400 uppercase tracking-widest w-1/2">Descripción</th>
                            <th class="pb-4 text-xs font-black text-gray-400 uppercase tracking-widest text-center">Cant</th>
                            <th class="pb-4 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Precio</th>
                            <th class="pb-4 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($items as $i): ?>
                        <tr class="print-break">
                            <td class="py-4 pr-4">
                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($i['nombre_producto']); ?></p>
                                <?php if($i['descripcion']): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo nl2br(htmlspecialchars($i['descripcion'])); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center font-bold text-gray-600"><?php echo floatval($i['cantidad']); ?></td>
                            <td class="py-4 px-4 text-right font-bold text-gray-600">$<?php echo number_format($i['precio_unitario'], 2); ?></td>
                            <td class="py-4 pl-4 text-right font-black text-gray-900">$<?php echo number_format($i['cantidad'] * $i['precio_unitario'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end mb-16 print-break">
                <div class="w-full md:w-1/2 lg:w-1/3 space-y-3">
                    <div class="flex justify-between text-sm font-bold text-gray-500">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($factura['subtotal'], 2); ?></span>
                    </div>
                    <?php if($factura['impuestos'] > 0): ?>
                    <div class="flex justify-between text-sm font-bold text-gray-500">
                        <span>Impuestos</span>
                        <span>$<?php echo number_format($factura['impuestos'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="h-px bg-gray-200 my-2"></div>
                    <div class="flex justify-between text-xl font-black text-gray-900">
                        <span>Total</span>
                        <span style="color: <?php echo $color; ?>">$<?php echo number_format($factura['total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if($factura['notas']): ?>
            <div class="bg-gray-50 p-8 rounded-3xl border border-gray-100 print-break">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Notas y Condiciones</h3>
                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($factura['notas'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Attachments -->
            <?php if(!empty($archivos)): ?>
            <div class="mt-8 bg-blue-50 p-8 rounded-3xl border border-blue-100 print-break">
                <h3 class="text-xs font-black text-blue-400 uppercase tracking-widest mb-4">Documentos Adjuntos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach($archivos as $file): ?>
                    <a href="<?php echo htmlspecialchars($file['ruta_archivo']); ?>" target="_blank" class="flex items-center gap-3 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-blue-100 group">
                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($file['nombre_original']); ?></p>
                            <p class="text-xs text-blue-500 font-medium">Clic para descargar</p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-12 text-center text-xs text-gray-400 font-bold uppercase tracking-widest">
                Gracias por su confianza
            </div>

        </div>
    </div>

</body>
</html>
