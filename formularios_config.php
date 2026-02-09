<?php
require_once 'includes/auth_helper.php';
require_once 'db.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: formularios.php");
    exit;
}

// Fetch Form
$stmt = $pdo->prepare("SELECT * FROM formularios WHERE id = ?");
$stmt->execute([$id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Formulario no encontrado.");
}

// Fetch Fields for Shortcodes
$stmtF = $pdo->prepare("SELECT * FROM formulario_campos WHERE formulario_id = ? ORDER BY order_index ASC");
$stmtF->execute([$id]);
$fields = $stmtF->fetchAll(PDO::FETCH_ASSOC);

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_template = $_POST['email_template'] ?? '';
    $whatsapp_template = $_POST['whatsapp_template'] ?? '';
    $email_recipients = $_POST['email_recipients'] ?? '';
    $whatsapp_recipients = $_POST['whatsapp_recipients'] ?? '';
    $advanced_notifications = $_POST['advanced_notifications'] ?? '[]';
    
    $stmtU = $pdo->prepare("UPDATE formularios SET email_template = ?, whatsapp_template = ?, email_recipients = ?, whatsapp_recipients = ?, advanced_notifications = ? WHERE id = ?");
    $stmtU->execute([$email_template, $whatsapp_template, $email_recipients, $whatsapp_recipients, $advanced_notifications, $id]);
    
    header("Location: formularios_config.php?id=$id&success=1");
    exit;
}

require_once 'includes/header.php';
?>
<script>
    const allFields = <?php echo json_encode($fields); ?>;
</script>

<div class="max-w-5xl mx-auto space-y-8 pb-20">
    <div class="flex items-center justify-between">
        <div>
            <a href="formularios.php" class="text-xs font-bold text-gray-400 hover:text-indigo-600 flex items-center gap-1 mb-2 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path></svg>
                VOLVER A FORMULARIOS
            </a>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Configuración de Notificaciones</h1>
            <p class="text-gray-500 font-medium">Personaliza los mensajes automáticos para <strong><?php echo htmlspecialchars($form['titulo']); ?></strong></p>
        </div>
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-emerald-50 text-emerald-600 px-6 py-3 rounded-2xl text-sm font-bold animate-in fade-in slide-in-from-right-4">
                ¡Configuración guardada!
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Sidebar: Shortcodes -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-[2.5rem] border border-gray-100 p-8 shadow-sm sticky top-24">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-6">Atajos disponibles</h3>
                <p class="text-[10px] text-gray-400 leading-relaxed mb-6">Usa estos códigos en tus plantillas para insertar dinámicamente las respuestas de tus clientes.</p>
                
                <div class="space-y-3 overflow-y-auto max-h-[50vh] pr-2 custom-scrollbar">
                    <?php foreach($fields as $field): 
                        if(in_array($field['type'], ['step', 'section', 'title', 'image', 'html'])) continue;
                        $shortcode = '{{' . ($field['label'] ?: $field['name']) . '}}';
                    ?>
                        <div class="group p-3 rounded-2xl bg-gray-50 border border-transparent hover:border-indigo-100 hover:bg-indigo-50 transition-all cursor-pointer" onclick="insertShortcode('<?php echo $shortcode; ?>')">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black text-gray-400 group-hover:text-indigo-400 uppercase tracking-wider"><?php echo strtoupper($field['type']); ?></span>
                                <span class="text-[9px] font-mono text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-lg group-hover:bg-white"><?php echo $shortcode; ?></span>
                            </div>
                            <p class="text-xs font-bold text-gray-700 mt-1 truncate"><?php echo htmlspecialchars($field['label']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-8 border-t border-gray-50">
                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Códigos Globales</h4>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 cursor-pointer hover:text-indigo-500" onclick="insertShortcode('{{FORM_NAME}}')">
                            <span>Nombre Formulario</span>
                            <code class="text-indigo-500">{{FORM_NAME}}</code>
                        </div>
                        <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 cursor-pointer hover:text-indigo-500" onclick="insertShortcode('{{SUBMIT_DATE}}')">
                            <span>Fecha de envío</span>
                            <code class="text-indigo-500">{{SUBMIT_DATE}}</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content: Templates -->
        <div class="lg:col-span-2 space-y-8">
            <form action="" method="POST" class="space-y-8">
                
                <!-- Email Template -->
                <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden min-h-[400px]">
                    <div class="p-8 border-b border-gray-50 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-gray-900 tracking-tight">Plantilla de Email</h3>
                                <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">Notificación por Correo Electrónico</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="mb-6">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Correos Receptores (Separados por coma)</label>
                            <input type="text" name="email_recipients" value="<?php echo htmlspecialchars($form['email_recipients'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-5 py-3 text-xs font-bold text-slate-700 outline-none focus:ring-4 focus:ring-indigo-500/5 focus:bg-white transition-all" placeholder="admin@empresa.com, ventas@empresa.com">
                        </div>
                        <textarea name="email_template" rows="12" class="w-full bg-slate-50 border border-slate-100 rounded-3xl p-6 text-sm font-medium text-slate-700 outline-none focus:ring-4 focus:ring-indigo-500/5 focus:bg-white transition-all resize-none" placeholder="Hola [field_1], hemos recibido tu solicitud..."><?php echo htmlspecialchars($form['email_template'] ?? ''); ?></textarea>
                        <p class="mt-4 text-[10px] text-gray-400 italic">Si dejas este campo vacío, se enviará una notificación estándar con todos los campos.</p>
                    </div>
                </div>

                <!-- WhatsApp Template -->
                <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden min-h-[400px]">
                    <div class="p-8 border-b border-gray-50 flex items-center justify-between bg-emerald-50/30">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500 flex items-center justify-center text-white shadow-lg shadow-emerald-100">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.27 9.27 0 01-4.487-1.164l-.322-.19-3.338.875.89-3.251-.208-.332A9.28 9.28 0 012.225 9.37C2.228 4.225 6.42 0 11.57 0a11.5 11.5 0 018.175 3.385 11.455 11.455 0 013.387 8.19c-.002 5.143-4.194 9.366-9.345 9.366z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-gray-900 tracking-tight">Plantilla de WhatsApp</h3>
                                <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">Notificación por Chat</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="mb-6">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Celulares Receptores (Separados por coma)</label>
                            <input type="text" name="whatsapp_recipients" value="<?php echo htmlspecialchars($form['whatsapp_recipients'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-5 py-3 text-xs font-bold text-slate-700 outline-none focus:ring-4 focus:ring-emerald-500/5 focus:bg-white transition-all" placeholder="5731000000, 5255000000">
                        </div>
                        <textarea name="whatsapp_template" rows="12" class="w-full bg-slate-50 border border-slate-100 rounded-3xl p-6 text-sm font-medium text-slate-700 outline-none focus:ring-4 focus:ring-emerald-500/5 focus:bg-white transition-all resize-none" placeholder="*Nuevo mensaje de:* [field_1]..."><?php echo htmlspecialchars($form['whatsapp_template'] ?? ''); ?></textarea>
                        <p class="mt-4 text-[10px] text-gray-400 italic">Si dejas este campo vacío, no se enviará notificación por WhatsApp.</p>
                    </div>
                </div>

                <!-- WhatsApp Template (Existing) -->
                <!-- ... existing code ... -->

                <!-- Advanced Notifications Wrapper -->
                <div class="space-y-6 pt-10 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-black text-gray-900 tracking-tight">Notificaciones Avanzadas</h2>
                            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Envía mensajes basados en la lógica de las respuestas</p>
                        </div>
                        <button type="button" onclick="addNotificationRule()" class="bg-indigo-600 text-white px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-95 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Nueva Regla
                        </button>
                    </div>

                    <div id="advanced-notifications-list" class="space-y-8">
                        <!-- Rules Injected by JS -->
                    </div>
                    
                    <input type="hidden" name="advanced_notifications" id="advanced-notifications-input" value='<?php echo $form['advanced_notifications'] ?: "[]"; ?>'>
                </div>

                <div class="flex justify-end gap-6 pt-10 border-t border-gray-100">
                    <a href="formularios.php" class="px-10 py-4 rounded-2xl text-gray-400 font-black text-xs uppercase tracking-widest hover:text-gray-600 transition-colors">Cancelar</a>
                    <button type="submit" class="px-12 py-4 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-slate-200 hover:bg-indigo-600 transition-all active:scale-95">Guardar Cambios</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
let lastFocusedInput = null;

document.addEventListener('focusin', (e) => {
    if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') {
        lastFocusedInput = e.target;
    }
});

function insertShortcode(text) {
    if (!lastFocusedInput) {
        // Fallback to copy if no input focused
        navigator.clipboard.writeText(text);
        alert('Atajo copiado: ' + text);
        return;
    }

    const start = lastFocusedInput.selectionStart;
    const end = lastFocusedInput.selectionEnd;
    const val = lastFocusedInput.value;
    
    lastFocusedInput.value = val.substring(0, start) + text + val.substring(end);
    
    // Resume focus and cursor position
    lastFocusedInput.focus();
    lastFocusedInput.selectionStart = lastFocusedInput.selectionEnd = start + text.length;
}

// Advanced Notifications Logic
let notificationsData = JSON.parse(document.getElementById('advanced-notifications-input').value || '[]');

function syncAdvancedNotifications() {
    document.getElementById('advanced-notifications-input').value = JSON.stringify(notificationsData);
}

function addNotificationRule() {
    const newRule = {
        enabled: true,
        match: 'all',
        rules: [],
        email: { recipients: '', template: '' },
        whatsapp: { recipients: '', template: '' }
    };
    notificationsData.push(newRule);
    renderNotificationRules();
    syncAdvancedNotifications();
}

function removeNotificationRule(idx) {
    notificationsData.splice(idx, 1);
    renderNotificationRules();
    syncAdvancedNotifications();
}

function addRuleCondition(ruleIdx) {
    notificationsData[ruleIdx].rules.push({ field: '', op: 'equals', value: '' });
    renderNotificationRules();
    syncAdvancedNotifications();
}

function updateRuleBasic(ruleIdx, key, val) {
    notificationsData[ruleIdx][key] = val;
    syncAdvancedNotifications();
}

function updateRuleData(ruleIdx, type, key, val) {
    notificationsData[ruleIdx][type][key] = val;
    syncAdvancedNotifications();
}

function updateCondition(ruleIdx, condIdx, key, val) {
    notificationsData[ruleIdx].rules[condIdx][key] = val;
    if(key === 'field') {
        const field = allFields.find(f => f.name === val || f.id == val);
        // Reset sub-type UI if needed - but here we'll just re-render to get correct value type
        renderNotificationRules(); 
    }
    syncAdvancedNotifications();
}

function renderNotificationRules() {
    const container = document.getElementById('advanced-notifications-list');
    container.innerHTML = '';

    notificationsData.forEach((rule, rIdx) => {
        const div = document.createElement('div');
        div.className = 'bg-white rounded-[2rem] border-2 border-indigo-50 shadow-xl shadow-indigo-100/50 p-8 space-y-6 animate-in fade-in slide-in-from-top-4 duration-500';
        
        // Header with Delete and Toggle
        let headerHtml = `
            <div class="flex items-center justify-between border-b border-gray-50 pb-6 mb-2">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-black shadow-lg shadow-indigo-100">${rIdx + 1}</span>
                    <h4 class="text-sm font-black text-gray-800 uppercase tracking-widest">Regla de Envío</h4>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-xl border border-gray-100">
                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Activa</span>
                        <input type="checkbox" ${rule.enabled ? 'checked' : ''} onchange="updateRuleBasic(${rIdx}, 'enabled', this.checked)" class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                    </div>
                    <button type="button" onclick="removeNotificationRule(${rIdx})" class="p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </div>
        `;

        // Logic Builder Part
        let fieldsOptions = '<option value="">(Selecciona Campo)</option>';
        allFields.forEach(f => {
            if(!['step', 'section', 'title', 'image', 'html'].includes(f.type)) {
                const val = f.name || f.id;
                fieldsOptions += `<option value="${val}" ${rule.rules.find(r => r.field == val) ? '' : ''}>${f.label}</option>`;
            }
        });

        let conditionsHtml = rule.rules.map((c, cIdx) => {
            const fieldS = allFields.find(f => (f.name === c.field || f.id == c.field));
            let valInput = `<input type="text" value="${c.value || ''}" oninput="updateCondition(${rIdx}, ${cIdx}, 'value', this.value)" class="flex-1 bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Valor">`;
            
            if(fieldS && ['select', 'radio', 'checkbox'].includes(fieldS.type)) {
                const opts = JSON.parse(fieldS.options || '[]');
                if(opts.length > 0) {
                    valInput = `
                        <select onchange="updateCondition(${rIdx}, ${cIdx}, 'value', this.value)" class="flex-1 bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">(Selecciona)</option>
                            ${opts.map(o => `<option value="${o}" ${o === c.value ? 'selected' : ''}>${o}</option>`).join('')}
                        </select>
                    `;
                }
            }

            return `
                <div class="flex items-center gap-2 group/cond">
                    <select onchange="updateCondition(${rIdx}, ${cIdx}, 'field', this.value)" class="flex-1 bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                        ${fieldsOptions.replace(`value="${c.field}"`, `value="${c.field}" selected`)}
                    </select>
                    <select onchange="updateCondition(${rIdx}, ${cIdx}, 'op', this.value)" class="w-32 bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="equals" ${c.op==='equals'?'selected':''}>Es igual</option>
                        <option value="not_equals" ${c.op==='not_equals'?'selected':''}>No es igual</option>
                        <option value="contains" ${c.op==='contains'?'selected':''}>Contiene</option>
                        <option value="greater" ${c.op==='greater'?'selected':''}>Mayor</option>
                        <option value="less" ${c.op==='less'?'selected':''}>Menor</option>
                    </select>
                    ${valInput}
                    <button type="button" onclick="notificationsData[${rIdx}].rules.splice(${cIdx}, 1); renderNotificationRules(); syncAdvancedNotifications();" class="text-gray-300 hover:text-red-400 p-1 opacity-0 group-hover/cond:opacity-100 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            `;
        }).join('');

        let logicHtml = `
            <div class="space-y-4 bg-slate-50/50 p-6 rounded-3xl border border-slate-100">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Condición de Envío</span>
                        <div class="flex items-center gap-2">
                            <p class="text-[11px] font-bold text-slate-600">Enviar si</p>
                            <select onchange="updateRuleBasic(${rIdx}, 'match', this.value)" class="bg-indigo-50 border-none text-indigo-600 font-black text-[10px] rounded-lg px-2 py-0.5 outline-none cursor-pointer">
                                <option value="all" ${rule.match==='all'?'selected':''}>TODAS</option>
                                <option value="any" ${rule.match==='any'?'selected':''}>CUALQUIERA</option>
                            </select>
                            <p class="text-[11px] font-bold text-slate-600">las reglas coinciden:</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    ${conditionsHtml}
                    ${rule.rules.length === 0 ? '<p class="text-[10px] text-slate-400 font-bold italic py-2">Se enviará siempre (sin condiciones)</p>' : ''}
                </div>
                <button type="button" onclick="addRuleCondition(${rIdx})" class="flex items-center gap-2 text-[10px] font-black text-indigo-500 uppercase tracking-widest bg-white border border-indigo-50 px-4 py-2 rounded-xl hover:bg-white hover:border-indigo-200 transition-all shadow-sm">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Añadir Condición
                </button>
            </div>
        `;

        // Message Templates Inside the Rule
        let templatesHtml = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Advanced Email -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Email Personalizado</span>
                    </div>
                    <div class="space-y-3">
                        <input type="text" oninput="updateRuleData(${rIdx}, 'email', 'recipients', this.value)" value="${rule.email.recipients}" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Destinatarios (ej: {{Email}})">
                        <textarea oninput="updateRuleData(${rIdx}, 'email', 'template', this.value)" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-4 py-4 text-xs font-medium text-slate-600 outline-none focus:ring-2 focus:ring-indigo-500" rows="5" placeholder="Cuerpo del mensaje...">${rule.email.template}</textarea>
                    </div>
                </div>

                <!-- Advanced WhatsApp -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.27 9.27 0 01-4.487-1.164l-.322-.19-3.338.875.89-3.251-.208-.332A9.28 9.28 0 012.225 9.37C2.228 4.225 6.42 0 11.57 0a11.5 11.5 0 018.175 3.385 11.455 11.455 0 013.387 8.19c-.002 5.143-4.194 9.366-9.345 9.366z"/></svg>
                        </div>
                        <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">WhatsApp Personalizado</span>
                    </div>
                    <div class="space-y-3">
                        <input type="text" oninput="updateRuleData(${rIdx}, 'whatsapp', 'recipients', this.value)" value="${rule.whatsapp.recipients}" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Destinatarios (ej: {{Celular}})">
                        <textarea oninput="updateRuleData(${rIdx}, 'whatsapp', 'template', this.value)" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-4 py-4 text-xs font-medium text-slate-600 outline-none focus:ring-2 focus:ring-emerald-500" rows="5" placeholder="Cuerpo del mensaje...">${rule.whatsapp.template}</textarea>
                    </div>
                </div>
            </div>
        `;

        div.innerHTML = headerHtml + logicHtml + templatesHtml;
        container.appendChild(div);
    });
}

// Initial Render
document.addEventListener('DOMContentLoaded', () => {
    renderNotificationRules();
});
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<?php require_once 'includes/footer.php'; ?>
