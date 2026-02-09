<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
$empresa_id = getEmpresaId();

// Handle Embedded Login Redirect
if (!$empresa_id) {
    if (isset($_GET['embedded']) && $_GET['embedded'] == 1) {
        die('<div style="padding:20px;text-align:center;font-family:sans-serif;">Sesión expirada. <a href="login.php" target="_blank">Iniciar Sesión</a></div>');
    }
    header("Location: login.php");
    exit;
}

// Logic for Embedded Widget (Standalone Mode)
$is_embedded = (isset($_GET['embedded']) && $_GET['embedded'] == 1);

if ($is_embedded) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once 'includes/auth_helper.php';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: white; height: 100vh; overflow: hidden; }
            .hidden { display: none; }
            /* Custom Scrollbar */
            .custom-scrollbar::-webkit-scrollbar { width: 6px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
            
            /* Embedded Overrides - DESIGN AGENT PROTOCOL (Ultra-Robust) */
            html, body { 
                height: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                overflow: hidden !important; 
                display: flex !important;
                flex-direction: column !important;
            }
            
            /* The main container must fill the body flex */
            .glass-card { 
                flex: 1 1 0% !important;
                width: 100% !important; 
                border-radius: 0 !important; 
                border: none !important; 
                box-shadow: none !important; 
                display: flex !important;
                flex-direction: column !important;
                position: relative !important;
                overflow: hidden !important;
            }
            
            #chatListPanel { 
                flex: 1 1 0% !important;
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                width: 100% !important;
                position: relative !important;
                background: white !important;
            }

            /* Header stays fixed at top */
            #chatListPanel > div:first-child {
                flex: 0 0 auto !important;
                background: white !important;
                border-bottom: 1px solid #f3f4f6;
                z-index: 20 !important;
            }
            
            /* List container fills remaining flex space and anchors absolute child */
            #chatListContainer {
                flex: 1 1 0% !important;
                position: relative !important;
                min-height: 0 !important;
            }

            /* The actual list is pinned to fill the container */
            #chatList { 
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                overflow-y: auto !important;
                padding-bottom: 20px !important;
            }
            
            #chatWindow { display: none !important; }
            #emptyState { display: none !important; }
            
            /* Modal Fixes */
            .fixed.inset-0.z-50 { position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; }
            
            /* Debug log - Hidden */
            #debug-log { display: none !important; }
        </style>
    </head>
    <body class="bg-white">
        <!-- Debug Log Container (Hidden) -->
        <div id="debug-log" style="display:none;"></div>
        
    <?php
    // We intentionally do NOT include header.php here
} else {
    include 'includes/header.php';
}
?>
<!-- START OF CONTENT -->
<script>
function logDebug(msg) {
    // Visual debug disabled for production
    /*
    const d = document.getElementById('debug-log');
    if(d) {
        d.style.display = 'block';
        d.innerHTML += "<div>" + msg + "</div>";
    }
    */
    console.log("DEBUG:", msg);
}
</script>


<!-- Librería de Emojis -->
<script src="https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js"></script>

<!-- Main Container -->
<div class="flex overflow-hidden glass-card">
    <!-- Sidebar de Chats -->
    <!-- Sidebar de Chats -->
    <div id="chatListPanel" class="w-full md:w-80 lg:w-96 flex flex-col border-r border-gray-100 bg-white/70 backdrop-blur-xl">
        <div class="px-4 py-3 space-y-3">
            <div class="flex items-center justify-between">
                <!-- Title completely removed to save space -->
                <div class="flex-1"></div>
                <div class="flex gap-1">
                    <button onclick="document.getElementById('newChatModal').classList.remove('hidden')" class="p-1.5 hover:bg-indigo-50 rounded-lg transition-all text-indigo-600 group" title="Nuevo Chat">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                    <button onclick="loadChats()" class="p-1.5 hover:bg-gray-100 rounded-lg transition-all text-gray-400" title="Actualizar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>
            </div>
            
            <div class="relative">
                <input type="text" id="chatSearch" onkeyup="filterChats()" placeholder="Buscar cliente..." class="w-full bg-gray-100/80 border-none rounded-xl py-2 px-9 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>

        <!-- Lista de Chats -->
        <div id="chatListContainer" class="flex-1 relative">
            <div id="chatList" class="space-y-0.5 p-2 custom-scrollbar">
                <div class="flex flex-col items-center justify-center h-full text-gray-400 space-y-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                    <p class="text-[10px] font-black uppercase tracking-widest">Sincronizando...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Area de Chat -->
    <div id="chatWindow" class="hidden flex flex-1 flex-col bg-[#e5ddd5]/30 relative">
        <!-- Wallpaper de fondo (opcional) -->
        <div class="absolute inset-0 z-0 bg-[url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png')] bg-repeat opacity-[0.05] pointer-events-none"></div>

        <!-- Header del Chat -->
        <div class="relative z-10 p-4 bg-white/90 backdrop-blur-md border-b border-gray-100 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <!-- Botón Volver (Móvil) -->
                <button onclick="hideMobileChat()" class="md:hidden p-2 -ml-2 text-gray-400 hover:text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <div id="activeChatAvatar" class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-black text-lg shadow-lg border-2 border-white">
                    ?
                </div>
                <div>
                    <h3 id="activeChatName" class="font-black text-gray-900 tracking-tight text-base">Cargando...</h3>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <p id="activeChatStatus" class="text-[9px] font-black text-gray-400 uppercase tracking-widest">En línea</p>
                    </div>
                </div>
            </div>
            <div class="flex gap-1">
                <?php if (tienePermiso('eliminar_chat')): ?>
                <button onclick="deleteCurrentChat()" class="p-2 hover:bg-red-50 rounded-xl transition-all text-gray-400 hover:text-red-500" title="Eliminar Chat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mensajes -->
        <div id="messagesContainer" class="relative z-10 flex-1 overflow-y-auto p-4 md:p-8 space-y-6 flex flex-col scroll-smooth custom-scrollbar">
            <!-- Los mensajes se cargan aquí -->
        </div>

        <!-- Input Area -->
        <div class="relative z-10 p-4 bg-white/95 backdrop-blur-md border-t border-gray-100 shadow-[0_-4px_10px_rgba(0,0,0,0.02)]">
            <form id="messageForm" class="flex items-center gap-3">
                <button type="button" id="emojiButton" class="p-3 text-gray-400 hover:bg-gray-100 rounded-full transition-all hover:text-yellow-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                <div class="relative">
                    <button type="button" onclick="toggleAttachments(event)" class="p-3 text-gray-400 hover:bg-gray-100 rounded-full transition-all hover:text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                    </button>
                    <!-- Menú de Adjuntos -->
                    <div id="attachmentMenu" class="absolute bottom-14 left-0 bg-white rounded-2xl shadow-2xl border border-gray-100 w-56 overflow-hidden hidden transform origin-bottom-left transition-all z-50">
                        <div class="p-2 space-y-1">
                            <button type="button" onclick="triggerFileUpload('image')" class="w-full text-left px-4 py-3 hover:bg-indigo-50 rounded-xl flex items-center gap-3 text-gray-600 hover:text-indigo-600 transition-colors">
                                <span class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></span>
                                <span class="font-bold text-xs uppercase tracking-wide">Imagen / Video</span>
                            </button>
                            <button type="button" onclick="triggerFileUpload('document')" class="w-full text-left px-4 py-3 hover:bg-indigo-50 rounded-xl flex items-center gap-3 text-gray-600 hover:text-indigo-600 transition-colors">
                                <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></span>
                                <span class="font-bold text-xs uppercase tracking-wide">Documento</span>
                            </button>
                            <button type="button" onclick="openQuoteSelector()" class="w-full text-left px-4 py-3 hover:bg-indigo-50 rounded-xl flex items-center gap-3 text-gray-600 hover:text-indigo-600 transition-colors">
                                <span class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></span>
                                <span class="font-bold text-xs uppercase tracking-wide">Cotización</span>
                            </button>
                            <button type="button" onclick="openOTSelector()" class="w-full text-left px-4 py-3 hover:bg-indigo-50 rounded-xl flex items-center gap-3 text-gray-600 hover:text-indigo-600 transition-colors">
                                <span class="w-8 h-8 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></span>
                                <span class="font-bold text-xs uppercase tracking-wide">Orden de Trabajo</span>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Input Principal -->
                <input type="text" id="messageInput" autocomplete="off" placeholder="Escribe un mensaje..." class="flex-1 bg-gray-100/50 border-none rounded-2xl py-3.5 px-6 text-sm font-medium focus:ring-2 focus:ring-indigo-500 transition-all outline-none relative z-20">
                
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white p-3.5 rounded-full shadow-xl shadow-indigo-100 transition-all active:scale-90 flex items-center justify-center w-12 h-12">
                    <svg class="w-6 h-6 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="flex-1 flex flex-col items-center justify-center text-center p-12 space-y-8 bg-gray-50/50">
        <div class="relative">
            <div class="w-40 h-40 bg-white rounded-[4rem] flex items-center justify-center text-indigo-500 shadow-2xl animate-float">
                <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
            <div class="absolute -bottom-4 -right-4 w-12 h-12 bg-green-400 rounded-full border-4 border-white shadow-xl flex items-center justify-center text-white">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path></svg>
            </div>
        </div>
        <div class="max-w-md space-y-3">
            <h2 class="text-3xl font-black text-gray-900 tracking-tight">CRM WhatsApp <span class="text-indigo-600">Pro</span></h2>
            <p class="text-gray-500 font-medium leading-relaxed">Conéctate con tus clientes en tiempo real. Gestiona chats, envía propuestas y resuelve dudas al instante.</p>
            <button onclick="document.getElementById('newChatModal').classList.remove('hidden')" class="mt-4 px-8 py-4 bg-indigo-600 text-white rounded-2xl font-black shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">Iniciar Nueva Conversación</button>
        </div>
    </div>
</div>

<style>
/* Bubble Styles */
.bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.bubble-in {
    align-self: flex-start;
    background: white;
    color: #1a1a1a;
    border-top-left-radius: 2px;
}
.bubble-out {
    align-self: flex-end;
    background: #dcf8c6;
    color: #0b141a;
    border-top-right-radius: 2px;
}
.bubble-meta {
    font-size: 10px;
    opacity: 0.6;
    margin-top: 4px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 4px;
}
.date-divider {
    align-self: center;
    background: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
    color: #54656f;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 1px 1px rgba(0,0,0,0.05);
    margin: 16px 0;
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.animate-float { animation: float 4s ease-in-out infinite; }
.chat-item.active { background: rgba(79, 70, 229, 0.1) !important; color: #4f46e5 !important; }
.chat-item:hover:not(.active) { background: #f8fafc; }
</style>

<!-- Estilos adicionales para móvil y menús -->
<style>
/* CSS para móvil */
@media (max-width: 768px) {
    #chatListPanel { width: 100% !important; display: block; }
    #chatWindow { 
        width: 100% !important; 
        display: none; 
        position: fixed; 
        top: 64px; 
        bottom: 70px; 
        left: 0; 
        right: 0; 
        z-index: 40; 
        background: white; 
    }
    #chatWindow.active { display: flex; }
    #chatListPanel.hidden-mobile { display: none; }
    
    /* Asegurar que el input no se oculte */
    #chatWindow .p-4.bg-white\/95 {
        padding-bottom: 1.5rem;
    }
}

/* Menú Contextual */
.context-menu {
    position: absolute;
    right: 10px;
    top: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    z-index: 50;
    min-width: 160px;
    overflow: hidden;
    border: 1px solid #f3f4f6;
    display: none;
}
.context-menu.active { display: block; animation: fadeIn 0.15s ease-out; }
.context-menu button {
    width: 100%;
    text-align: left;
    padding: 10px 16px;
    font-size: 13px;
    color: #374151;
    transition: background 0.1s;
}
.context-menu button:hover { background: #f9fafb; color: #4f46e5; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- Modal Nuevo Chat -->
<div id="newChatModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="glass-card bg-white p-8 max-w-md w-full space-y-6 shadow-2xl">
        <div class="text-center space-y-2">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">Nuevo Mensaje</h3>
            <p class="text-gray-500 text-sm">Ingresa el número con código de país<br><span class="font-bold text-indigo-600">(ej: +573001234567)</span></p>
        </div>
        
        <form id="newChatForm" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Teléfono</label>
                <input type="text" id="newChatPhone" required placeholder="+57300..." class="w-full px-4 py-4 rounded-xl bg-gray-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Mensaje Inicial</label>
                <textarea id="newChatMessage" required rows="3" placeholder="Hola, me gustaría saludarte..." class="w-full px-4 py-4 rounded-xl bg-gray-50 border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('newChatModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-500 font-bold py-4 rounded-xl hover:bg-gray-200 transition-all">Cancelar</button>
                <button type="submit" class="flex-1 bg-indigo-600 text-white font-bold py-4 rounded-xl shadow-lg hover:bg-indigo-700 transition-all">Enviar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Asignar Agente (Multi-select) -->
<div id="assignModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="glass-card bg-white p-6 max-w-sm w-full space-y-4 shadow-2xl rounded-2xl flex flex-col max-h-[80vh]">
        <h3 class="text-xl font-black text-gray-900">Asignar Agentes</h3>
        <p class="text-xs text-gray-400">Selecciona los asesores para este chat.</p>
        
        <div id="agentList" class="space-y-2 overflow-y-auto flex-1 min-h-[150px] p-1">
            <!-- Checkboxes injected by JS -->
            <div class="animate-pulse flex space-y-2 flex-col">
                <div class="h-10 bg-gray-100 rounded-xl w-full"></div>
                <div class="h-10 bg-gray-100 rounded-xl w-full"></div>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button onclick="document.getElementById('assignModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-500 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Registrar Cliente (Full) -->
<div id="leadModal" class="fixed inset-0 z-[60] hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300 flex flex-col max-h-[90vh]">
        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 flex-shrink-0">
            <h3 class="text-xl font-bold text-gray-900">Registrar Cliente</h3>
            <button onclick="document.getElementById('leadModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form id="formClientChat" onsubmit="event.preventDefault();" class="p-8 space-y-4 overflow-y-auto custom-scrollbar">
            <!-- Hidden Fields -->
            <input type="hidden" id="leadChatId">
            <input type="hidden" id="leadSaveMode" value="save">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Nombre Completo / Razón Social</label>
                <input type="text" id="leadNombre" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">NIT / Identificación</label>
                    <input type="text" id="leadIdentificacion" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">País / Código</label>
                    <select id="leadPais" onchange="updateLeadCountry(this.value)" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                        <option value="57">Colombia (+57)</option>
                        <option value="1">USA (+1)</option>
                        <option value="34">España (+34)</option>
                        <option value="52">México (+52)</option>
                        <option value="54">Argentina (+54)</option>
                        <option value="56">Chile (+56)</option>
                        <option value="51">Perú (+51)</option>
                        <option value="593">Ecuador (+593)</option>
                        <option value="507">Panamá (+507)</option>
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
                    <input type="text" id="leadNombreContacto" placeholder="Ej: Juan Pérez" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Celular</label>
                        <div class="relative">
                            <span id="leadLabelCode" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">+57</span>
                            <input type="text" id="leadCelular" placeholder="3001234567" class="w-full pl-14 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Cargo</label>
                        <input type="text" id="leadCargo" placeholder="Opcional" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all bg-white text-sm">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Teléfono</label>
                    <input type="text" id="leadTelefono" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" id="leadEmail" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Dirección</label>
                <input type="text" id="leadDireccion" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="mt-2 flex items-center gap-2 p-3 bg-indigo-50/50 rounded-xl border border-indigo-100">
                <input type="checkbox" id="leadNotify" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 cursor-pointer" checked>
                <label for="leadNotify" class="text-xs font-bold text-gray-700 cursor-pointer select-none">
                    Enviar mensaje de bienvenida
                </label>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-6 pt-2">
                <button type="button" onclick="confirmLead('save')" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-4 rounded-2xl transition-all active:scale-95">
                    Solo Guardar
                </button>
                <button type="button" onclick="confirmLead('quote')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-2xl shadow-lg transition-all active:scale-95">
                    Guardar y Cotizar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Input File Oculto -->
<input type="file" id="fileInput" class="hidden" onchange="handleFileSelect(this)">

<!-- Modal Cotizaciones -->
<div id="quoteModal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="glass-card bg-white p-6 max-w-md w-full space-y-4 shadow-2xl rounded-2xl flex flex-col max-h-[80vh]">
        <h3 class="text-xl font-black text-gray-900">Seleccionar Cotización</h3>
        <div id="quoteList" class="space-y-2 overflow-y-auto flex-1 min-h-[150px]">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
            </div>
        </div>
        <button onclick="document.getElementById('quoteModal').classList.add('hidden')" class="w-full bg-gray-100 text-gray-500 font-bold py-3 rounded-xl">Cancelar</button>
    </div>
</div>

<!-- Modal Ordenes -->
<div id="otModal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="glass-card bg-white p-6 max-w-md w-full space-y-4 shadow-2xl rounded-2xl flex flex-col max-h-[80vh]">
        <h3 class="text-xl font-black text-gray-900">Seleccionar Orden</h3>
        <div id="otList" class="space-y-2 overflow-y-auto flex-1 min-h-[150px]">
             <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
            </div>
        </div>
        <button onclick="document.getElementById('otModal').classList.add('hidden')" class="w-full bg-gray-100 text-gray-500 font-bold py-3 rounded-xl">Cancelar</button>
    </div>
</div>

<script>
// Attachment Logic
function toggleAttachments(e) {
    const menu = document.getElementById('attachmentMenu');
    menu.classList.toggle('hidden');
    e.stopPropagation();
    
    // Close on out click
    const closeMenu = () => {
        menu.classList.add('hidden');
        document.removeEventListener('click', closeMenu);
    };
    if (!menu.classList.contains('hidden')) {
        setTimeout(() => document.addEventListener('click', closeMenu), 100);
    }
}

function triggerFileUpload(type) {
    const input = document.getElementById('fileInput');
    if (type === 'image') input.accept = "image/*,video/*";
    else input.accept = ".pdf,.doc,.docx,.xls,.xlsx,.txt";
    input.click();
}

async function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    if (!currentChatId) return alert("Selecciona un chat primero");

    const file = input.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_and_send');
    formData.append('chat_id', currentChatId);
    
    // Get phone from current chat object
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;
    formData.append('telefono', chat.whatsapp_id);
    formData.append('file', file);

    // Show uploading state
    const btn = document.querySelector('button[onclick="triggerFileUpload(\'image\')"]').parentNode.parentNode.previousElementSibling; // The clip button
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>';
    btn.disabled = true;

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            loadMessages();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { console.error(e); alert('Error de conexión'); }
    finally {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        input.value = ''; // Reset
    }
}

async function openQuoteSelector() {
    if (!currentChatId) return alert("Selecciona un chat");
    document.getElementById('quoteModal').classList.remove('hidden');
    const container = document.getElementById('quoteList');
    
    try {
        const res = await fetch(`api_crm_whatsapp.php?action=get_client_quotes&chat_id=${currentChatId}`);
        const data = await res.json();
        
        if (data.status === 'success' && data.data.length > 0) {
            container.innerHTML = data.data.map(q => `
                <div onclick="sendQuote(${q.id})" class="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition-all flex justify-between items-center group">
                    <div>
                        <p class="font-bold text-gray-900 text-sm">#${q.numero_cotizacion || q.id}</p>
                        <p class="text-xs text-gray-500">$${parseFloat(q.total).toLocaleString()} - ${q.estado}</p>
                    </div>
                    <button class="bg-indigo-50 text-indigo-600 p-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-center text-gray-400 text-xs py-4">No hay cotizaciones recientes</p>';
        }
    } catch(e) { container.innerHTML = '<p class="text-red-400 text-center text-xs">Error al cargar</p>'; }
}

async function sendQuote(id) {
    if (!confirm("¿Enviar esta cotización?")) return;
    document.getElementById('quoteModal').classList.add('hidden');
    
    // Optimistic loading? Maybe not necessary for quick action
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;

    const formData = new FormData();
    formData.append('action', 'send_quote_link');
    formData.append('cot_id', id);
    formData.append('chat_id', currentChatId);
    formData.append('telefono', chat.whatsapp_id);

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') loadMessages();
        else alert('Error: ' + data.message);
    } catch(e) { alert('Error enviando cotización'); }
}

async function openOTSelector() {
    if (!currentChatId) return alert("Selecciona un chat");
    document.getElementById('otModal').classList.remove('hidden');
    const container = document.getElementById('otList');
    
    try {
        const res = await fetch(`api_crm_whatsapp.php?action=get_client_ots&chat_id=${currentChatId}`);
        const data = await res.json();
        
        if (data.status === 'success' && data.data.length > 0) {
            container.innerHTML = data.data.map(o => `
                <div onclick="sendOT(${o.id})" class="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition-all flex justify-between items-center group">
                    <div>
                        <p class="font-bold text-gray-900 text-sm">#${o.numero_ot || o.id}</p>
                        <p class="text-xs text-gray-500">${o.modelo_dispositivo} - ${o.estado}</p>
                    </div>
                     <button class="bg-indigo-50 text-indigo-600 p-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            `).join('');
        } else {
             container.innerHTML = '<p class="text-center text-gray-400 text-xs py-4">No hay órdenes recientes</p>';
        }
    } catch(e) { container.innerHTML = '<p class="text-red-400 text-center text-xs">Error al cargar</p>'; }
}

async function sendOT(id) {
    if (!confirm("¿Enviar esta orden?")) return;
    document.getElementById('otModal').classList.add('hidden');
    
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;

    const formData = new FormData();
    formData.append('action', 'send_ot_link');
    formData.append('ot_id', id);
    formData.append('chat_id', currentChatId);
    formData.append('telefono', chat.whatsapp_id);

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') loadMessages();
        else alert('Error: ' + data.message);
    } catch(e) { alert('Error enviando orden'); }
}

// Variables globales
const userPermissions = (<?php echo json_encode($_SESSION['permisos'] ?? []); ?>) || [];
const canDeleteChat = userPermissions.includes('eliminar_chat') || <?php echo ($_SESSION['is_super_admin'] ?? false) ? 'true' : 'false'; ?>;

let currentChatId = null;
let actionChatId = null; // Para acciones de menú
let pollMessagesInterval = null;
let allChats = [];

document.addEventListener('click', (e) => {
    // Cerrar menús al hacer click fuera
    if (!e.target.closest('.context-menu') && !e.target.closest('button[onclick^="toggleMenu"]')) {
        document.querySelectorAll('.context-menu').forEach(el => el.classList.remove('active'));
    }
});

// Inicialización de Emoji Picker de forma segura
// Initial Loader
(function() {
    // Helper to run init logic
    async function initApp() {
        logDebug("InitApp Started");
        
        // Emoji Picker try-catch
        try {
            if (typeof EmojiButton !== 'undefined') {
                const picker = new EmojiButton({
                    position: 'top-start',
                    theme: 'light',
                    autoHide: false,
                    showSearch: true,
                    showPreview: false
                });
                const emojiBtn = document.querySelector('#emojiButton');
                const msgInput = document.querySelector('#messageInput');

                if (emojiBtn && msgInput) {
                    picker.on('emoji', selection => {
                        msgInput.value += selection.emoji;
                        msgInput.focus();
                    });
                    emojiBtn.onclick = (e) => {
                        e.preventDefault();
                        picker.togglePicker(emojiBtn);
                    };
                }
            } else {
                logDebug("EmojiButton not loaded - skipping");
            }
        } catch (e) {
            logDebug("Emoji Init Error: " + e.message);
        }

        // Load Chats
        try {
            await loadChats();
            logDebug("Initial loadChats completed");
            
            // Auto open logic
            const urlParams = new URLSearchParams(window.location.search);
            const phoneParam = urlParams.get('telefono');
            if (phoneParam) {
                const cleanPhone = phoneParam.replace(/\D/g, '');
                const foundChat = allChats.find(c => c.whatsapp_id.includes(cleanPhone));
                
                if (foundChat) {
                    selectChat(foundChat);
                } else {
                    document.getElementById('newChatModal').classList.remove('hidden');
                    document.getElementById('newChatPhone').value = '+' + cleanPhone;
                }
            }

            // Start Polling
            setInterval(loadChats, 10000);

        } catch(e) {
            logDebug("Critical App Start Error: " + e.message);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp(); // Already ready
    }
})();



async function loadChats() {
    try {
        logDebug("Iniciando loadChats...");
        const res = await fetch('api_crm_whatsapp.php?action=get_chats');
        logDebug("Fetch status: " + res.status);
        
        if (!res.ok) {
            throw new Error("Error en servidor: " + res.status);
        }
        const raw = await res.text();
        // logDebug("Raw length: " + raw.length);
        
        let data;
        try {
            data = JSON.parse(raw);
        } catch(pe) {
            logDebug("JSON Parse Error: " + pe.message);
            throw new Error("Respuesta no válida del servidor");
        }

        if (data.status === 'success') {
            allChats = data.data || [];
            logDebug("Chats cargados: " + allChats.length);
            renderChats(allChats);
        } else {
            logDebug("API Error: " + data.message);
            const container = document.getElementById('chatList');
            if (container) {
                container.innerHTML = `<div class="text-center py-12 px-4 text-orange-400"><p class="font-bold text-xs uppercase tracking-widest mb-2">Aviso del Sistema</p><p class="text-xs text-gray-500">${data.message}</p></div>`;
            }
        }
    } catch (e) { 
        logDebug("Catch Error: " + e.message); 
        const container = document.getElementById('chatList');
        if (container) {
            container.innerHTML = `<div class="text-center py-12 px-4 text-red-500"><p class="font-bold text-xs uppercase tracking-widest mb-2">Error de Conexión</p><p class="text-xs text-gray-400">${e.message}</p></div>`;
        }
    }
}

function renderChats(chats) {
    const container = document.getElementById('chatList');
    container.innerHTML = '';
    
    if (chats.length === 0) {
        container.innerHTML = '<div class="text-center py-12 text-gray-400"><p class="text-[10px] font-black uppercase tracking-widest">Sin conversaciones</p></div>';
        return;
    }

    chats.forEach(chat => {
        const isSelected = currentChatId == chat.id;
        const div = document.createElement('div');
        div.className = `chat-item p-4 flex items-center gap-3 cursor-pointer border-b border-gray-50 transition-all ${isSelected ? 'active' : ''}`;
        div.id = `chat-${chat.id}`;
        // Removed onclick from main div to handle specific clicks
        
        const lastMsgTime = chat.fecha_ultimo_mensaje ? formatTime(chat.fecha_ultimo_mensaje) : '';
        const initial = chat.cliente_nombre ? chat.cliente_nombre.charAt(0) : '?';
        
        // Render avatars for assigned agents
        let assignedHtml = '';
        if (chat && chat.asignados && Array.isArray(chat.asignados) && chat.asignados.length > 0) {
            assignedHtml = `<div class="flex -space-x-1.5 ml-2">`;
            chat.asignados.forEach(a => {
                if(a && a.nombre) {
                    assignedHtml += `
                        <div class="w-5 h-5 rounded-full bg-gray-100 border border-white flex items-center justify-center text-[8px] font-bold text-gray-600" title="${a.nombre}">
                            ${a.nombre.charAt(0)}
                        </div>
                    `;
                }
            });
            assignedHtml += `</div>`;
        }

        div.innerHTML = `
            <div class="relative w-12 h-12 flex-shrink-0">
                <div class="w-full h-full rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center font-black text-white text-lg shadow-sm">
                    ${initial}
                </div>
                ${!chat.can_see_phone && !chat.cliente_nombre ? '<div class="absolute -bottom-1 -right-1 bg-gray-600 text-white text-[8px] px-1 rounded-md" title="Número oculto">🔒</div>' : ''}
            </div>
            <div class="flex-1 min-w-0" onclick="selectChatById(${chat.id})">
                <div class="flex justify-between items-start mb-0.5">
                    <h4 class="font-black text-gray-900 truncate tracking-tight text-[13px]">${chat.cliente_nombre || chat.whatsapp_display}</h4>
                    <span class="text-[9px] font-black text-gray-400 uppercase ml-2">${lastMsgTime}</span>
                </div>
                <div class="flex justify-between items-center">
                    <p class="text-[11px] text-gray-500 truncate flex-1 leading-tight">${chat.ultimo_mensaje || 'Inicia conversación...'}</p>
                    ${!chat.visto_por_admin ? '<div class="w-2 h-2 bg-indigo-500 rounded-full ml-2"></div>' : ''}
                    ${assignedHtml}
                </div>
            </div>
            <!-- Menú 3 puntos -->
            <div class="relative ml-2">
                <button onclick="toggleMenu(event, ${chat.id})" class="p-1.5 rounded-full hover:bg-gray-100 text-gray-400 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                </button>
                <div id="menu-${chat.id}" class="context-menu">
                    <button onclick="openAssignModal(${chat.id})">Asignar Agente</button>
                    ${!chat.cliente_nombre ? `<button onclick="openLeadModal(${chat.id})">Guardar Cliente</button>` : ''}
                    <button onclick="alert('Próximamente: ver cotizaciones')">Ver Cotizaciones</button>
                    ${canDeleteChat ? `<button onclick="deleteCurrentChat(${chat.id})">Eliminar Chat</button>` : ''}
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function filterChats() {
    const q = document.getElementById('chatSearch').value.toLowerCase();
    const filtered = allChats.filter(c => 
        (c.cliente_nombre && c.cliente_nombre.toLowerCase().includes(q)) || 
        c.whatsapp_id.includes(q)
    );
    renderChats(filtered);
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    } catch (e) { return ''; }
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return '';
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (d >= today) return "Hoy";
        if (d >= yesterday) return "Ayer";
        return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'long' });
    } catch (e) { return ''; }
}

// Funciones de UI
function toggleMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.context-menu').forEach(el => el.classList.remove('active'));
    document.getElementById(`menu-${id}`)?.classList.toggle('active');
}

function selectChatById(id) {
    const chat = allChats.find(c => c.id == id);
    if (chat) selectChat(chat);
}

// Mobile Nav
function showMobileChat() {
    if (window.innerWidth <= 768) {
        document.getElementById('chatWindow').classList.add('active');
        document.getElementById('chatWindow').classList.remove('hidden');
        document.getElementById('chatListPanel').classList.add('hidden-mobile');
    }
}

function hideMobileChat() {
    document.getElementById('chatWindow').classList.remove('active');
    document.getElementById('chatWindow').classList.add('hidden');
    document.getElementById('chatListPanel').classList.remove('hidden-mobile');
    currentChatId = null;
    if (pollMessagesInterval) clearInterval(pollMessagesInterval);
    // Remover clase active de la lista para poder re-seleccionar
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
}

// Logic for Modals
async function openAssignModal(id) {
    actionChatId = id;
    const list = document.getElementById('agentList');
    list.innerHTML = '<div class="flex justify-center p-4"><div class="w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div></div>';
    document.getElementById('assignModal').classList.remove('hidden');
    
    try {
        const res = await fetch('api_crm_whatsapp.php?action=get_agents');
        const data = await res.json();
        
        const chat = allChats.find(c => c.id == id);
        const asignadosIds = chat && chat.asignados ? chat.asignados.map(a => a.id) : [];

        if (data.status === 'success') {
            list.innerHTML = data.data.map(a => {
                const isChecked = asignadosIds.some(aid => aid == a.id);
                return `
                <label class="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors border border-transparent hover:border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs">
                            ${a.nombre.charAt(0)}
                        </div>
                        <span class="font-medium text-gray-700 text-sm">${a.nombre}</span>
                    </div>
                    <div class="relative">
                        <input type="checkbox" onchange="toggleAgentAssignment(${id}, ${a.id}, this)" 
                               ${isChecked ? 'checked' : ''} 
                               class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    </div>
                </label>`;
            }).join('');
        }
    } catch(e) { console.error(e); list.innerHTML = '<p class="text-red-500 text-center text-xs">Error al cargar</p>'; }
}

async function toggleAgentAssignment(chatId, agentId, checkbox) {
    const originalState = !checkbox.checked;
    const mode = checkbox.checked ? 'add' : 'remove';
    checkbox.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'assign_chat');
        formData.append('chat_id', chatId);
        formData.append('agent_id', agentId);
        formData.append('mode', mode);

        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            loadChats(); // Reload to update avatars in list
        } else {
            checkbox.checked = originalState; 
            alert('Error: ' + data.message);
        }
    } catch (e) {
        console.error(e);
        checkbox.checked = originalState;
    } finally {
        checkbox.disabled = false;
    }
}

function updateLeadCountry(code) {
    document.getElementById('leadLabelCode').innerText = '+' + code;
}

function openLeadModal(id) {
    actionChatId = id;
    const chat = allChats.find(c => c.id == id);
    
    // Pre-fill phone in celular_contacto if possible
    document.getElementById('leadCelular').value = chat ? formatWhatsAppId(chat.whatsapp_id, false) : '';
    document.getElementById('leadNombre').value = '';
    document.getElementById('leadIdentificacion').value = '';
    document.getElementById('leadNombreContacto').value = '';
    document.getElementById('leadCargo').value = '';
    document.getElementById('leadTelefono').value = '';
    document.getElementById('leadEmail').value = '';
    document.getElementById('leadDireccion').value = '';

    document.getElementById('leadModal').classList.remove('hidden');
}

async function confirmLead(mode) {
    const nombre = document.getElementById('leadNombre').value;
    if (!nombre) return alert('Ingresa el Nombre o Razón Social');

    const btn = event.target; // The button clicked
    const originalText = btn.innerText;
    if (btn.innerText !== 'Guardando...') {
       btn.disabled = true;
       btn.innerText = 'Guardando...';
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'save_full_client');
        formData.append('chat_id', actionChatId);
        formData.append('nombre', nombre);
        formData.append('identificacion', document.getElementById('leadIdentificacion').value);
        formData.append('pais_codigo', document.getElementById('leadPais').value);
        formData.append('nombre_contacto', document.getElementById('leadNombreContacto').value);
        formData.append('celular_contacto', document.getElementById('leadCelular').value);
        formData.append('cargo_contacto', document.getElementById('leadCargo').value);
        formData.append('telefono', document.getElementById('leadTelefono').value);
        formData.append('email', document.getElementById('leadEmail').value);
        formData.append('direccion', document.getElementById('leadDireccion').value);
        
        // Notify flag
        if (document.getElementById('leadNotify').checked) {
            formData.append('enviar_notificacion', '1');
        }
        
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success' || data.status === 'duplicate_linked') {
            document.getElementById('leadModal').classList.add('hidden');
            
            if (data.status === 'duplicate_linked') {
                alert(`Aviso: ${data.message}`);
            } else {
                alert('Cliente guardado exitosamente.');
            }

            if (mode === 'quote' && data.cliente_id) {
                window.location.href = `nueva-cotizacion.php?cliente_id=${data.cliente_id}`;
            } else {
                loadChats();
            }
        } else {
            alert('Error: ' + data.message);
        }
    } catch(e) { 
        console.error(e);
        alert('Error al guardar cliente'); 
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }
}

// Helper to clean phone for display input
function formatWhatsAppId(wid, includePlus) {
    if(!wid) return '';
    return wid.replace(/\D/g, ''); 
}

// Modificar selectChat para móvil
async function selectChat(chat) {
    currentChatId = chat.id;
    showMobileChat(); // Activar vista móvil
    
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('chatWindow').classList.remove('hidden');
    
    // UI Update
    document.getElementById('activeChatName').innerText = chat.cliente_nombre || chat.whatsapp_display;
    document.getElementById('activeChatAvatar').innerText = chat.cliente_nombre ? chat.cliente_nombre.charAt(0) : '?';
    
    // Clear list selection
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    document.getElementById(`chat-${chat.id}`)?.classList.add('active');

    await loadMessages();
    loadChats(); // Actualizar badges
    
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;

    if (pollMessagesInterval) clearInterval(pollMessagesInterval);
    pollMessagesInterval = setInterval(loadMessages, 5000);
}

async function loadMessages() {
    if (!currentChatId) return;
    try {
        const res = await fetch(`api_crm_whatsapp.php?action=get_messages&chat_id=${currentChatId}`);
        if (!res.ok) throw new Error("Server error: " + res.status);
        const data = await res.json();
        if (data.status === 'success' && Array.isArray(data.data)) {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            if (data.data.length === 0) {
                container.innerHTML = `
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-300 py-20 px-4">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-center">Sin mensajes aún</p>
                        <p class="text-[9px] text-gray-400 mt-1 text-center">Envía el primer mensaje para iniciar la conversación</p>
                    </div>
                `;
                return;
            }

            const atBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
            
            let html = '';
            let lastDate = '';

            data.data.forEach(msg => {
                const dateLabel = formatDateLabel(msg.fecha_envio);
                if (dateLabel !== lastDate) {
                    html += `<div class="date-divider">${dateLabel}</div>`;
                    lastDate = dateLabel;
                }

                const isIn = msg.direccion == 'entrante';
                html += `
                    <div class="group flex flex-col ${isIn ? 'items-start' : 'items-end'}" id="msg-${msg.id}">
                        <div class="bubble ${isIn ? 'bubble-in' : 'bubble-out'} group-hover:shadow-md transition-all">
                            ${!isIn ? `<div class="text-[9px] font-black uppercase mb-1 text-black/40">${msg.asesor_nombre || 'Yo'}</div>` : ''}
                            <div class="whitespace-pre-wrap">${msg.contenido || ''}</div>
                            <div class="bubble-meta">
                                ${formatTime(msg.fecha_envio)}
                                ${!isIn ? '<svg class="w-3.5 h-3.5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 12l4 4L18 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 12l4 4L22 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' : ''}
                            </div>
                        </div>
                        ${canDeleteChat ? `<button onclick="deleteMessage(${msg.id})" class="text-[9px] font-black text-gray-400 opacity-0 group-hover:opacity-100 uppercase mt-1 hover:text-red-500 transition-all">Eliminar</button>` : ''}
                    </div>
                `;
            });
            container.innerHTML = html;
            
            if (atBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }
    } catch (e) {}
}

async function deleteMessage(id) {
    if (!confirm("¿Borrar este mensaje?")) return;
    const body = new FormData();
    body.append('action', 'delete_message');
    body.append('id', id);
    const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body });
    const data = await res.json();
    if (data.status === 'success') {
        document.getElementById(`msg-${id}`).remove();
    }
}

async function deleteCurrentChat() {
    if (!confirm("¿Eliminar toda la conversación? Esta acción no se puede deshacer.")) return;
    const body = new FormData();
    body.append('action', 'delete_chat');
    body.append('id', currentChatId);
    const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body });
    const data = await res.json();
    if (data.status === 'success') {
        location.reload();
    }
}

// Attachment Logic
function toggleAttachments(e) {
    const menu = document.getElementById('attachmentMenu');
    if (!menu) return;
    menu.classList.toggle('hidden');
    e.stopPropagation();
    
    const closeMenu = () => {
        menu.classList.add('hidden');
        document.removeEventListener('click', closeMenu);
    };
    if (!menu.classList.contains('hidden')) {
        setTimeout(() => document.addEventListener('click', closeMenu), 100);
    }
}

function triggerFileUpload(type) {
    const input = document.getElementById('fileInput');
    if (!input) return;
    if (type === 'image') input.accept = "image/*,video/*";
    else input.accept = ".pdf,.doc,.docx,.xls,.xlsx,.txt";
    input.click();
}

async function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    if (!currentChatId) return alert("Selecciona un chat primero");

    const file = input.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_and_send');
    formData.append('chat_id', currentChatId);
    
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;
    formData.append('telefono', chat.whatsapp_id);
    formData.append('file', file);

    const btn = document.querySelector('button[onclick="toggleAttachments(event)"]');
    if (!btn) return;
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>';
    btn.disabled = true;

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            await loadMessages();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { console.error(e); alert('Error de conexión'); }
    finally {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        input.value = '';
    }
}

async function openQuoteSelector() {
    if (!currentChatId) return alert("Selecciona un chat");
    document.getElementById('quoteModal').classList.remove('hidden');
    const container = document.getElementById('quoteList');
    if (!container) return;
    
    try {
        const res = await fetch(`api_crm_whatsapp.php?action=get_client_quotes&chat_id=${currentChatId}`);
        const data = await res.json();
        
        if (data.status === 'success' && data.data.length > 0) {
            container.innerHTML = data.data.map(q => `
                <div onclick="sendQuote(${q.id})" class="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition-all flex justify-between items-center group">
                    <div>
                        <p class="font-bold text-gray-900 text-sm">#${q.numero_cotizacion || q.id}</p>
                        <p class="text-xs text-gray-500">$${parseFloat(q.total).toLocaleString()} - ${q.estado}</p>
                    </div>
                    <button class="bg-indigo-50 text-indigo-600 p-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-center text-gray-400 text-xs py-4">No hay cotizaciones recientes</p>';
        }
    } catch(e) { container.innerHTML = '<p class="text-red-400 text-center text-xs">Error al cargar</p>'; }
}

async function sendQuote(id) {
    if (!confirm("¿Enviar esta cotización?")) return;
    document.getElementById('quoteModal').classList.add('hidden');
    
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;

    const formData = new FormData();
    formData.append('action', 'send_quote_link');
    formData.append('cot_id', id);
    formData.append('chat_id', currentChatId);
    formData.append('telefono', chat.whatsapp_id);

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') await loadMessages();
        else alert('Error: ' + data.message);
    } catch(e) { alert('Error enviando cotización'); }
}

async function openOTSelector() {
    if (!currentChatId) return alert("Selecciona un chat");
    document.getElementById('otModal').classList.remove('hidden');
    const container = document.getElementById('otList');
    if (!container) return;
    
    try {
        const res = await fetch(`api_crm_whatsapp.php?action=get_client_ots&chat_id=${currentChatId}`);
        const data = await res.json();
        
        if (data.status === 'success' && data.data.length > 0) {
            container.innerHTML = data.data.map(o => `
                <div onclick="sendOT(${o.id})" class="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition-all flex justify-between items-center group">
                    <div>
                        <p class="font-bold text-gray-900 text-sm">#${o.numero_ot || o.id}</p>
                        <p class="text-xs text-gray-500">${o.modelo_dispositivo} - ${o.estado}</p>
                    </div>
                     <button class="bg-indigo-50 text-indigo-600 p-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            `).join('');
        } else {
             container.innerHTML = '<p class="text-center text-gray-400 text-xs py-4">No hay órdenes recientes</p>';
        }
    } catch(e) { container.innerHTML = '<p class="text-red-400 text-center text-xs">Error al cargar</p>'; }
}

async function sendOT(id) {
    if (!confirm("¿Enviar esta orden?")) return;
    document.getElementById('otModal').classList.add('hidden');
    
    const chat = allChats.find(c => c.id == currentChatId);
    if (!chat) return;

    const formData = new FormData();
    formData.append('action', 'send_ot_link');
    formData.append('ot_id', id);
    formData.append('chat_id', currentChatId);
    formData.append('telefono', chat.whatsapp_id);

    try {
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') await loadMessages();
        else alert('Error: ' + data.message);
    } catch(e) { alert('Error enviando orden'); }
}
document.getElementById('newChatForm').onsubmit = async (e) => {
    e.preventDefault();
    const phone = document.getElementById('newChatPhone').value.trim();
    const message = document.getElementById('newChatMessage').value.trim();
    const btn = e.target.querySelector('button[type="submit"]');

    if (!phone || !message) return alert("Completa los campos");
    
    btn.disabled = true;
    btn.innerHTML = 'Enviando...';

    try {
        const formData = new FormData();
        formData.append('action', 'create_new_chat'); 
        formData.append('phone', phone);
        formData.append('message', message);
        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            document.getElementById('newChatModal').classList.add('hidden');
            document.getElementById('newChatForm').reset();
            await loadChats();
            const newChat = allChats.find(c => c.id == data.chat_id);
            if (newChat) selectChat(newChat);
        } else {
            alert(data.message);
        }
    } catch (e) { alert("Error de conexión al crear chat"); }
    finally {
        btn.disabled = false;
        btn.innerHTML = 'Enviar';
    }
};

document.getElementById('messageForm').onsubmit = async (e) => {
    e.preventDefault();
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text || !currentChatId) return;

    input.value = '';
    input.setAttribute('disabled', 'true');

    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('chat_id', currentChatId);
        formData.append('text', text);

        const res = await fetch('api_crm_whatsapp.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            await loadMessages();
            const container = document.getElementById('messagesContainer');
            if (container) container.scrollTop = container.scrollHeight;
        } else {
            alert("Error al enviar: " + data.message);
        }
    } catch (e) { alert("Error de conexión al enviar mensaje"); }
    finally {
        input.removeAttribute('disabled');
        input.focus();
    }
};

</script>

<?php 
if (isset($is_embedded) && $is_embedded) {
    echo '</body></html>';
} else {
    include 'includes/footer.php'; 
}
?>
