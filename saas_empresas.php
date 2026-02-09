<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Acciones CRUD para Empresas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        try {
            $pdo->beginTransaction();
            
            // 1. Crear Empresa
            $stmt = $pdo->prepare("INSERT INTO empresas (nombre, nit, plan_id) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['nit'], $_POST['plan_id']]);
            $empresa_id = $pdo->lastInsertId();
            
            // 2. Crear Rol Administrador por defecto para esta empresa
            $stmt = $pdo->prepare("INSERT INTO roles (empresa_id, nombre, descripcion) VALUES (?, 'Administrador', 'Acceso total a la empresa')");
            $stmt->execute([$empresa_id]);
            $rol_id = $pdo->lastInsertId();
            
            // 3. Asignar todos los permisos existentes al nuevo rol
            $permisos = $pdo->query("SELECT id FROM permisos")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)");
            foreach ($permisos as $p_id) {
                $stmt->execute([$rol_id, $p_id]);
            }
            
            // 4. Crear Usuario Administrador Inicial
            $pass_hash = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, rol_id, nombre, email, password, activo, requires_password_setup) VALUES (?, ?, ?, ?, ?, 1, 0)");
            $stmt->execute([$empresa_id, $rol_id, $_POST['admin_nombre'], $_POST['admin_email'], $pass_hash]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al crear empresa: " . $e->getMessage());
        }
    } elseif ($accion === 'editar') {
        $stmt = $pdo->prepare("UPDATE empresas SET nombre = ?, nit = ?, plan_id = ? WHERE id = ?");
        $stmt->execute([$_POST['nombre'], $_POST['nit'], $_POST['plan_id'], $_POST['id']]);
    } elseif ($accion === 'eliminar') {
        $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    } elseif ($accion === 'ingresar') {
        $_SESSION['empresa_id'] = $_POST['id'];
        header("Location: index.php");
        exit;
    }
    
    header("Location: saas_empresas.php");
    exit;
}

$empresas = $pdo->query("SELECT e.*, p.nombre as plan_nombre FROM empresas e LEFT JOIN planes p ON e.plan_id = p.id ORDER BY e.nombre ASC")->fetchAll();
$planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY id ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Gestión de Empresas</h1>
            <p class="text-gray-500 font-medium">Administra los clientes y sus suscripciones.</p>
        </div>
        <button onclick="abrirModalEmpresa()" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100">
            Nueva Empresa
        </button>
    </div>

    <div class="glass-card overflow-hidden">
        <table class="w-full text-left">
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($empresas as $emp): 
                    // Obtener uso actual
                    $uso_cots = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones WHERE empresa_id = ?");
                    $uso_cots->execute([$emp['id']]);
                    $count_cots = $uso_cots->fetchColumn();

                    $uso_users = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ?");
                    $uso_users->execute([$emp['id']]);
                    $count_users = $uso_users->fetchColumn();

                    // Obtener límites del plan
                    $plan_stmt = $pdo->prepare("SELECT limite_cotizaciones, limite_usuarios FROM planes WHERE id = ?");
                    $plan_stmt->execute([$emp['plan_id']]);
                    $limits = $plan_stmt->fetch() ?: ['limite_cotizaciones' => 0, 'limite_usuarios' => 0];
                ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($emp['nombre']); ?></div>
                            <div class="text-[10px] text-gray-400 font-medium">NIT: <?php echo $emp['nit']; ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="space-y-1">
                                <span class="px-2 py-1 bg-blue-50 text-blue-600 text-[9px] font-black uppercase rounded-lg block w-max">
                                    <?php echo $emp['plan_nombre'] ?: 'Sin Plan'; ?>
                                </span>
                                <div class="flex items-center gap-2 text-[9px] font-bold">
                                    <span class="text-gray-500">Cotizaciones:</span>
                                    <span class="<?php echo $count_cots >= $limits['limite_cotizaciones'] ? 'text-red-500' : 'text-indigo-600'; ?>">
                                        <?php echo $count_cots; ?> / <?php echo $limits['limite_cotizaciones']; ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-[9px] font-bold">
                                    <span class="text-gray-500">Usuarios:</span>
                                    <span class="<?php echo $count_users >= $limits['limite_usuarios'] ? 'text-red-500' : 'text-indigo-600'; ?>">
                                        <?php echo $count_users; ?> / <?php echo $limits['limite_usuarios']; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            Creada: <?php echo date('d/m/Y', strtotime($emp['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                             <div class="flex justify-end items-center gap-3">
                                <button onclick="verDetalles(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['nombre']); ?>')" class="text-[9px] font-black text-gray-500 hover:text-indigo-600 uppercase tracking-widest bg-gray-50 px-3 py-1.5 rounded-lg transition-all" title="Ver métricas detalladas">Detalles</button>
                                <button onclick="iniciarSoporte(<?php echo $emp['id']; ?>)" class="text-[9px] font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-widest bg-indigo-50 px-3 py-1.5 rounded-lg transition-all" title="Entrar como esta empresa">Soporte</button>
                                
                                <button onclick='editarEmpresa(<?php echo json_encode($emp); ?>)' class="text-gray-400 hover:text-indigo-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <form action="saas_empresas.php" method="POST" class="inline" onsubmit="return confirm('¿Eliminar empresa y todos sus datos?')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                    <button type="submit" class="text-gray-400 hover:text-red-600 p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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

<!-- Modal Detalles -->
<div id="modalDetalles" class="fixed inset-0 z-50 hidden bg-black/20 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] w-full max-w-2xl p-10 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
        
        <div class="flex justify-between items-start mb-8">
            <div>
                <h3 id="detallesNombre" class="text-3xl font-black text-gray-900 tracking-tighter">Nombre Empresa</h3>
                <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Métricas de Uso & Salud</p>
            </div>
            <button onclick="document.getElementById('modalDetalles').classList.add('hidden')" class="p-2 hover:bg-gray-100 rounded-xl transition-colors">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div id="detallesContenido" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Cargado por AJAX -->
        </div>

        <div class="mt-8 pt-8 border-t border-dashed border-gray-100">
            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Distribución de Cotizaciones</h4>
            <div id="detallesCotizaciones" class="flex gap-2 flex-wrap">
                <!-- Cargado por AJAX -->
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <p id="detallesUltima" class="text-[10px] font-bold text-gray-400 uppercase italic"></p>
        </div>
    </div>
</div>

<!-- Modal Empresa -->
<div id="modalEmpresa" class="fixed inset-0 z-50 hidden bg-black/20 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-md p-8 shadow-2xl">
        <h3 id="modalTitulo" class="text-2xl font-black text-gray-900 mb-6">Crear Empresa</h3>
        <form action="saas_empresas.php" method="POST" class="space-y-4">
            <input type="hidden" name="accion" id="inputAccion" value="crear">
            <input type="hidden" name="id" id="inputId">
            
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre Comercial</label>
                <input type="text" name="nombre" id="inputNombre" required class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">NIT / Identificación</label>
                <input type="text" name="nit" id="inputNit" class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all">
            </div>

            <div id="seccionAdmin" class="pt-4 border-t border-dashed border-gray-100 space-y-4">
                <p class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">Cuenta Administrador Inicial</p>
                
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre Admin</label>
                        <input type="text" name="admin_nombre" id="admin_nombre" class="w-full px-4 py-2 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all text-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Email Admin</label>
                        <input type="email" name="admin_email" id="admin_email" class="w-full px-4 py-2 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all text-sm">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Contraseña Admin</label>
                    <input type="password" name="admin_pass" id="admin_pass" class="w-full px-4 py-2 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all text-sm">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Plan Suscripción</label>
                <select name="plan_id" id="inputPlan" class="w-full px-4 py-3 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-white font-bold text-gray-700">
                    <?php foreach ($planes as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo $p['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('modalEmpresa').classList.add('hidden')" class="flex-1 px-4 py-3 rounded-xl border border-gray-100 font-bold text-gray-400 text-[10px] uppercase tracking-widest hover:bg-gray-50 transition-colors">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 font-bold text-white text-[10px] uppercase tracking-widest hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEmpresa() {
    document.getElementById('inputAccion').value = 'crear';
    document.getElementById('inputId').value = '';
    document.getElementById('inputNombre').value = '';
    document.getElementById('inputNit').value = '';
    document.getElementById('admin_nombre').value = '';
    document.getElementById('admin_email').value = '';
    document.getElementById('admin_pass').value = '';
    document.getElementById('seccionAdmin').classList.remove('hidden');
    document.getElementById('modalTitulo').innerText = 'Crear Empresa';
    document.getElementById('modalEmpresa').classList.remove('hidden');
}

function editarEmpresa(emp) {
    document.getElementById('inputAccion').value = 'editar';
    document.getElementById('inputId').value = emp.id;
    document.getElementById('inputNombre').value = emp.nombre;
    document.getElementById('inputNit').value = emp.nit;
    document.getElementById('inputPlan').value = emp.plan_id;
    document.getElementById('seccionAdmin').classList.add('hidden');
    document.getElementById('modalTitulo').innerText = 'Editar Empresa';
    document.getElementById('modalEmpresa').classList.remove('hidden');
}

async function verDetalles(id, nombre) {
    document.getElementById('detallesNombre').innerText = nombre;
    document.getElementById('detallesContenido').innerHTML = '<div class="col-span-full py-10 text-center text-gray-400 italic">Cargando métricas...</div>';
    document.getElementById('detallesCotizaciones').innerHTML = '';
    document.getElementById('modalDetalles').classList.remove('hidden');

    try {
        const res = await fetch(`api_saas_metrics.php?id=${id}`);
        const json = await res.json();
        
        if (json.status === 'success') {
            const d = json.data;
            
            // Render Cards
            document.getElementById('detallesContenido').innerHTML = `
                ${renderMetricCard('Usuarios', d.usuarios, 'bg-blue-50 text-blue-600')}
                ${renderMetricCard('Clientes', d.clientes, 'bg-green-50 text-green-600')}
                ${renderMetricCard('Órdenes', d.ordenes, 'bg-purple-50 text-purple-600')}
                ${renderMetricCard('Almacenamiento', formatBytes(d.storage), 'bg-amber-50 text-amber-600')}
            `;

            // Render Cotizaciones Tag Cloud
            const cots = d.cotizaciones || {};
            const statuses = Object.keys(cots);
            if (statuses.length > 0) {
                document.getElementById('detallesCotizaciones').innerHTML = statuses.map(s => `
                    <div class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center gap-2">
                        <span class="text-[10px] font-black text-gray-900 uppercase tracking-tighter">${s}</span>
                        <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-lg">${cots[s]}</span>
                    </div>
                `).join('');
            } else {
                document.getElementById('detallesCotizaciones').innerHTML = '<p class="text-[10px] text-gray-400 italic">Sin datos de cotizaciones</p>';
            }

            document.getElementById('detallesUltima').innerText = d.ultima_actividad ? `Última cotización: ${new Date(d.ultima_actividad).toLocaleString()}` : 'Sin actividad reciente';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('detallesContenido').innerHTML = '<div class="col-span-full py-10 text-center text-red-400">Error al cargar datos</div>';
    }
}

function renderMetricCard(label, value, colors) {
    return `
        <div class="p-6 rounded-3xl border border-gray-100 bg-white card-shadow">
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2">${label}</p>
            <p class="text-2xl font-black text-gray-900 tracking-tighter">${value}</p>
        </div>
    `;
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

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
