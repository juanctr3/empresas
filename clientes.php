<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Procesar Acciones Clientes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de validación de seguridad (CSRF).");
    }

    if (isset($_POST['accion'])) {
        $id = $_POST['id'] ?? null;
        $email = $_POST['email'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $accion_final = $_POST['accion'];

        // 1. Verificar Duplicados (Solo si hay email)
        if (!empty($email)) {
            $stmt_check = $pdo->prepare("SELECT id, es_cliente FROM clientes WHERE email = ? AND empresa_id = ? AND id != ?");
            $stmt_check->execute([$email, getEmpresaId(), $id ?: 0]);
            $existente = $stmt_check->fetch();

            if ($existente) {
                $_SESSION['error_duplicado'] = [
                    'id' => $existente['id'],
                    'nombre' => $nombre,
                    'tipo' => $existente['es_cliente'] ? 'Cliente' : 'Prospecto'
                ];
                header("Location: clientes.php");
                exit;
            }
        }

        if ($id && ($accion_final === 'editar_cliente' || $accion_final === 'crear_y_cotizar')) {
            // Actualizar
            $stmt = $pdo->prepare("UPDATE clientes SET nombre = ?, identificacion = ?, pais_codigo = ?, nombre_contacto = ?, cargo_contacto = ?, celular_contacto = ?, direccion = ?, telefono = ?, email = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([
                $nombre, $_POST['identificacion'], $_POST['pais_codigo'],
                $_POST['nombre_contacto'], $_POST['cargo_contacto'], $_POST['celular_contacto'],
                $_POST['direccion'], $_POST['telefono'], $email,
                $id, getEmpresaId()
            ]);
            $cliente_id = $id;
        } elseif ($accion_final === 'crear_cliente' || $accion_final === 'crear_y_cotizar') {
            // Insertar (como CLIENTE real, es_cliente = 1)
            $token = md5(uniqid(rand(), true));
            $stmt = $pdo->prepare("INSERT INTO clientes (empresa_id, usuario_id, nombre, identificacion, pais_codigo, nombre_contacto, cargo_contacto, celular_contacto, direccion, telefono, email, es_cliente, token_acceso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute([
                getEmpresaId(), $_SESSION['user_id'], $nombre, $_POST['identificacion'], $_POST['pais_codigo'],
                $_POST['nombre_contacto'], $_POST['cargo_contacto'], $_POST['celular_contacto'],
                $_POST['direccion'], $_POST['telefono'], $email, $token
            ]);
            $cliente_id = $pdo->lastInsertId();

            // Enviar Notificación de Bienvenida si se seleccionó
            if (!empty($_POST['enviar_notificacion'])) {
                require_once 'includes/client_notifications.php';
                enviarNotificacionBienvenida($pdo, $cliente_id);
            }
        } elseif ($accion_final === 'eliminar_cliente') {
            if (!tienePermiso('eliminar_cliente')) {
                die("No tienes permiso para eliminar clientes.");
            }
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['id'], getEmpresaId()]);
        }

        if (strpos($accion_final, 'y_cotizar') !== false && isset($cliente_id)) {
            header("Location: nueva-cotizacion.php?cliente_id=" . $cliente_id);
            exit;
        }

        header("Location: clientes.php");
        exit;
    }
}

// Obtener Datos
$stmt_cl = $pdo->prepare("SELECT * FROM clientes WHERE empresa_id = ? AND es_cliente = 1 ORDER BY nombre ASC");
$stmt_cl->execute([getEmpresaId()]);
$clientes = $stmt_cl->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Clientes</h1>
            <p class="text-gray-500 mt-1">Base de datos de clientes que han aprobado cotizaciones.</p>
        </div>
        <button onclick="abrirModalCliente()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-8 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            Nuevo Cliente
        </button>
    </div>

    <!-- Lista de Clientes -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($clientes)): ?>
            <div class="col-span-full bg-white p-12 rounded-3xl border border-dashed border-gray-300 text-center">
                <p class="text-gray-400 italic">No hay clientes registrados aún.</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($clientes as $cl): ?>
            <div class="bg-white p-6 rounded-3xl border border-gray-100 card-shadow hover:border-indigo-200 transition-all group relative">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center font-bold text-xl">
                        <?php echo strtoupper(substr($cl['nombre'], 0, 1)); ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if($cl['celular_contacto']): ?>
                            <a href="whatsapp.php?telefono=<?php echo $cl['pais_codigo'] . $cl['celular_contacto']; ?>" class="text-green-500 hover:text-green-600 transition-colors p-1" title="Chat CRM">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            </a>
                        <?php endif; ?>
                        <a href="perfil-cliente.php?id=<?php echo $cl['id']; ?>" class="text-indigo-600 hover:text-indigo-800 transition-colors p-1" title="Ver Perfil 360">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                        <button onclick='editarCliente(<?php echo json_encode($cl); ?>)' class="text-gray-300 hover:text-blue-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <?php if (tienePermiso('eliminar_cliente')): ?>
                        <form action="clientes.php" method="POST" onsubmit="return confirm('¿Eliminar cliente?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="accion" value="eliminar_cliente">
                            <input type="hidden" name="id" value="<?php echo $cl['id']; ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($cl['nombre']); ?></h3>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">
                    ID: <?php echo htmlspecialchars($cl['identificacion'] ?: 'N/A'); ?> 
                    <span class="ml-2 text-indigo-400">(+<?php echo htmlspecialchars($cl['pais_codigo']); ?>)</span>
                </p>
                
                <div class="space-y-2 text-sm text-gray-600">
                    <?php if($cl['nombre_contacto']): ?>
                    <div class="flex items-center gap-2 font-medium text-gray-800">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <?php echo htmlspecialchars($cl['nombre_contacto']); ?>
                        <?php if($cl['cargo_contacto']): ?>
                            <span class="text-[10px] bg-gray-100 px-2 py-0.5 rounded-full text-gray-500 uppercase"><?php echo htmlspecialchars($cl['cargo_contacto']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if($cl['celular_contacto']): ?>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        (+<?php echo htmlspecialchars($cl['pais_codigo'] ?? ''); ?>) <?php echo htmlspecialchars($cl['celular_contacto']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if($cl['telefono']): ?>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        <?php echo htmlspecialchars($cl['telefono']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if($cl['email']): ?>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($cl['email']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Portal Box -->
                <div class="mt-6 pt-6 border-t border-gray-50 flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Enlace al Portal</p>
                        <p class="text-[10px] font-bold text-indigo-600 truncate opacity-60">mi-area.php?t=<?php echo $cl['token_acceso']; ?></p>
                    </div>
                    <button onclick="copyPortalLink('<?php echo getBaseUrl(); ?>mi-area.php?t=<?php echo $cl['token_acceso']; ?>')" class="p-2 bg-indigo-50 text-indigo-500 rounded-xl hover:bg-indigo-500 hover:text-white transition-all shadow-sm flex-shrink-0 ml-4" title="Copiar Enlace al Portal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                    </button>
                </div>
            </div>
<?php endforeach; ?>
    </div>
</div>

<!-- Modal Cliente -->
<div id="modalCliente" class="fixed inset-0 z-[60] hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300">
        <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-xl font-bold text-gray-900" id="tituloModal">Registrar Cliente</h3>
            <button onclick="document.getElementById('modalCliente').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form action="clientes.php" method="POST" class="p-8 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion" id="inputAccion" value="crear_cliente">
            <input type="hidden" name="id" id="inputId" value="">
            
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Nombre Completo / Razón Social</label>
                <input type="text" name="nombre" id="inputNombre" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">NIT / Identificación</label>
                    <input type="text" name="identificacion" id="inputIdentificacion" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">País / Código</label>
                    <select name="pais_codigo" id="inputPais" onchange="updateCountryCode(this.value)" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                        <option value="57" data-flag="🇨🇴">Colombia (+57)</option>
                        <option value="1" data-flag="🇺🇸">USA (+1)</option>
                        <option value="34" data-flag="🇪🇸">España (+34)</option>
                        <option value="52" data-flag="🇲🇽">México (+52)</option>
                        <option value="54" data-flag="🇦🇷">Argentina (+54)</option>
                        <option value="56" data-flag="🇨🇱">Chile (+56)</option>
                        <option value="51" data-flag="🇵🇪">Perú (+51)</option>
                        <option value="593" data-flag="🇪🇨">Ecuador (+593)</option>
                        <option value="507" data-flag="🇵🇦">Panamá (+507)</option>
                    </select>
                </div>
            </div>

            <div class="bg-indigo-50/50 p-6 rounded-2xl border border-indigo-100 space-y-4">
                <h4 class="text-xs font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Información de Contacto
                </h4>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Nombre del Contacto</label>
                    <input type="text" name="nombre_contacto" id="inputNombreContacto" placeholder="Ej: Juan Pérez" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Celular</label>
                        <div class="relative">
                            <span id="labelCountryCode" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">+57</span>
                            <input type="text" name="celular_contacto" id="inputCelularContacto" placeholder="3001234567" class="w-full pl-14 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Cargo (Opcional)</label>
                        <input type="text" name="cargo_contacto" id="inputCargoContacto" placeholder="Ej: Gerente" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Teléfono</label>
                    <input type="text" name="telefono" id="inputTelefono" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" name="email" id="inputEmail" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Dirección</label>
                <input type="text" name="direccion" id="inputDireccion" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="mt-4 flex items-center gap-2 p-3 bg-indigo-50/50 rounded-xl border border-indigo-100">
                <input type="checkbox" name="enviar_notificacion" id="inputEnviarNotificacion" value="1" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 cursor-pointer" checked>
                <label for="inputEnviarNotificacion" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                    Enviar mensaje de bienvenida (Email y WhatsApp)
                </label>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <button type="submit" onclick="document.getElementById('inputAccion').value='crear_cliente'" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-4 rounded-2xl transition-all active:scale-95">
                    Solo Guardar
                </button>
                <button type="submit" onclick="document.getElementById('inputAccion').value='crear_y_cotizar'" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-2xl shadow-lg transition-all active:scale-95">
                    Guardar y Cotizar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputCelular = document.getElementById('inputCelularContacto');
    const inputTelefono = document.getElementById('inputTelefono');
    const inputEmail = document.getElementById('inputEmail');
    
    if (inputCelular && inputTelefono) {
        inputCelular.addEventListener('input', function() {
            // Solo auto-rellenar si el teléfono está vacío o es igual al celular anterior
            if (!inputTelefono.value || inputTelefono.value === inputCelular.value.substring(0, inputCelular.value.length - 1)) {
                inputTelefono.value = inputCelular.value;
            }
        });
        
        inputCelular.addEventListener('blur', function() {
            checkDuplicate('telefono', this.value);
        });
    }

    if (inputEmail) {
        inputEmail.addEventListener('blur', function() {
            checkDuplicate('email', this.value);
        });
    }
});

let isAlerting = false;
async function checkDuplicate(type, value) {
    if (!value || isAlerting) return;
    const excludeId = document.getElementById('inputId').value;
    
    try {
        const response = await fetch(`api_check_duplicate.php?${type}=${encodeURIComponent(value)}&exclude_id=${excludeId}`);
        const data = await response.json();
        
        const input = type === 'email' ? document.getElementById('inputEmail') : document.getElementById('inputCelularContacto');
        if (data.exists) {
            isAlerting = true;
            alert('⚠️ ATENCIÓN: ' + data.message);
            input.classList.add('border-red-500', 'ring-2', 'ring-red-200');
            setTimeout(() => { 
                input.focus();
                isAlerting = false; 
            }, 100);
        } else {
            input.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
            input.classList.add('border-green-500');
        }
    } catch (e) {
        console.error('Error verificando duplicados:', e);
        isAlerting = false;
    }
}

function updateCountryCode(code) {
    document.getElementById('labelCountryCode').innerText = '+' + code;
}

function abrirModalCliente() {
    document.getElementById('inputAccion').value = 'crear_cliente';
    document.getElementById('inputId').value = '';
    document.getElementById('inputNombre').value = '';
    document.getElementById('inputIdentificacion').value = '';
    document.getElementById('inputPais').value = '57';
    updateCountryCode('57');
    document.getElementById('inputNombreContacto').value = '';
    document.getElementById('inputCelularContacto').value = '';
    document.getElementById('inputCargoContacto').value = '';
    document.getElementById('inputTelefono').value = '';
    document.getElementById('inputEmail').value = '';
    document.getElementById('inputDireccion').value = '';
    document.getElementById('tituloModal').innerText = 'Registrar Cliente';
    document.getElementById('modalCliente').classList.remove('hidden');
}

function editarCliente(cl) {
    document.getElementById('inputAccion').value = 'editar_cliente';
    document.getElementById('inputId').value = cl.id;
    document.getElementById('inputNombre').value = cl.nombre;
    document.getElementById('inputIdentificacion').value = cl.identificacion;
    document.getElementById('inputPais').value = cl.pais_codigo || '57';
    updateCountryCode(cl.pais_codigo || '57');
    document.getElementById('inputNombreContacto').value = cl.nombre_contacto || '';
    document.getElementById('inputCelularContacto').value = cl.celular_contacto || '';
    document.getElementById('inputCargoContacto').value = cl.cargo_contacto || '';
    document.getElementById('inputTelefono').value = cl.telefono;
    document.getElementById('inputEmail').value = cl.email;
    document.getElementById('inputDireccion').value = cl.direccion;
    document.getElementById('tituloModal').innerText = 'Editar Cliente';
    document.getElementById('modalCliente').classList.remove('hidden');
}

function cerrarModalDuplicado() {
    document.getElementById('modalDuplicado').classList.add('hidden');
}

function copyPortalLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        alert('¡Enlace copiado al portapapeles!');
    }).catch(err => {
        console.error('Error al copiar: ', err);
    });
}
</script>

<!-- Modal Duplicado -->
<?php if (isset($_SESSION['error_duplicado'])): 
    $dup = $_SESSION['error_duplicado'];
    unset($_SESSION['error_duplicado']);
?>
<div id="modalDuplicado" class="fixed inset-0 z-[100] bg-gray-900/60 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-300">
    <div class="bg-white rounded-[2.5rem] w-full max-md overflow-hidden shadow-2xl scale-100 p-8 text-center space-y-6">
        <div class="w-20 h-20 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mx-auto shadow-inner">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        
        <div class="space-y-2">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">¡Email Duplicado!</h3>
            <p class="text-gray-500">El contacto <b><?php echo htmlspecialchars($dup['nombre']); ?></b> ya existe en el sistema como un <b><?php echo $dup['tipo']; ?></b>.</p>
        </div>

        <div class="flex flex-col gap-3">
            <a href="perfil-cliente.php?id=<?php echo $dup['id']; ?>" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
                Ir al Perfil del <?php echo $dup['tipo']; ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7"></path></svg>
            </a>
            <button onclick="cerrarModalDuplicado()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-4 rounded-2xl transition-all">
                Cerrar y Revisar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
