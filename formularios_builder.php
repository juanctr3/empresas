<?php
require_once 'includes/auth_helper.php';
require_once 'includes/header.php';
$id = $_GET['id'] ?? 0;
?>

<!-- Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<div class="h-[calc(100vh-64px)] flex bg-gray-50 overflow-hidden relative">

    <!-- DESKTOP LEFT SIDEBAR: COMPONENTS -->
    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-gray-200 z-20 shadow-sm relative shrink-0">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-xs font-black uppercase tracking-widest text-gray-500">Componentes</h2>
        </div>
        <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
            <div class="space-y-6">
                <!-- Group: Estructura -->
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase mb-3 block tracking-widest">Estructura</label>
                    <div class="grid grid-cols-2 gap-2">
                         <button onclick="addField('step')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 hover:text-indigo-600 transition-all group border border-transparent hover:border-indigo-100">
                            <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                            <span class="text-[9px] font-bold">Nuevo Paso</span>
                        </button>
                        <button onclick="addField('section')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 hover:text-indigo-600 transition-all group border border-transparent hover:border-indigo-100">
                            <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                            <span class="text-[9px] font-bold">Sección</span>
                        </button>
                    </div>
                </div>

                <!-- Group: Básicos -->
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase mb-3 block tracking-widest">Básicos</label>
                    <div class="space-y-2">
                        <button onclick="addField('text')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Texto Corto</span>
                        </button>
                        <button onclick="addField('full_name')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Nombre Completo</span>
                        </button>
                        <button onclick="addField('textarea')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Párrafo</span>
                        </button>
                         <button onclick="addField('number')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Número</span>
                        </button>
                         <button onclick="addField('document')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">NIT / Cédula</span>
                        </button>
                        <button onclick="addField('date')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4v-4m-9 18h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Fecha</span>
                        </button>
                    </div>
                </div>

                <!-- Group: Elección -->
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase mb-3 block tracking-widest">Elección</label>
                    <div class="space-y-2">
                        <button onclick="addField('select')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Desplegable</span>
                        </button>
                        <button onclick="addField('radio')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Opción Única</span>
                        </button>
                         <button onclick="addField('checkbox')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Casilla</span>
                        </button>
                    </div>
                </div>

                <!-- Group: Avanzado -->
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase mb-3 block tracking-widest">Avanzado</label>
                    <div class="space-y-2">
                        <button onclick="addField('phone')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Celular</span>
                        </button>
                        <button onclick="addField('address')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Dirección</span>
                        </button>
                        <button onclick="addField('email')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">E-mail</span>
                        </button>
                        <button onclick="addField('url')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">URL / Sitio Web</span>
                        </button>
                        <button onclick="addField('signature')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Firma digital</span>
                        </button>
                        <button onclick="addField('file')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Archivo</span>
                        </button>
                        <button onclick="addField('time')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Hora</span>
                        </button>
                         <button onclick="addField('product')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Producto</span>
                        </button>
                        <button onclick="addField('time_range')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-700">Franja Horaria</span>
                        </button>
                         <button onclick="addField('smart_field')" class="w-full flex items-center gap-3 p-3 rounded-xl bg-purple-50 hover:bg-purple-100 group transition-all border border-purple-100">
                            <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-purple-500 group-hover:text-purple-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-purple-700">Dato Cotización</span>
                        </button>
                    </div>
                </div>

                <!-- Group: Contenido -->
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase mb-3 block tracking-widest">Contenido</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="addField('title')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all border border-transparent hover:border-indigo-100">
                             <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                            <span class="text-[9px] font-bold">Título</span>
                        </button>
                        <button onclick="addField('image')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all border border-transparent hover:border-indigo-100">
                             <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="text-[9px] font-bold">Imagen</span>
                        </button>
                         <button onclick="addField('consent')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all border border-transparent hover:border-indigo-100">
                             <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-[9px] font-bold">Consent.</span>
                        </button>
                         <button onclick="addField('captcha')" class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 hover:bg-indigo-50 group transition-all border border-transparent hover:border-indigo-100">
                             <svg class="w-5 h-5 mb-1 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span class="text-[9px] font-bold">Seguridad</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- CENTER CANVAS AREA -->
    <main class="flex-1 flex flex-col relative min-w-0">
        <!-- Top Bar -->
        <div class="bg-white border-b border-gray-200 px-4 md:px-6 py-3 flex items-center justify-between shadow-sm z-10 shrink-0">
            <div class="flex items-center gap-3">
                <a href="formularios.php" class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div class="flex flex-col min-w-0">
                    <h1 id="form-title" class="text-sm md:text-base font-black text-gray-800 leading-tight truncate max-w-[150px] md:max-w-xs">Cargando...</h1>
                    <div class="flex items-center gap-2">
                         <span id="save-status" class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Sin cambios</span>
                         <!-- Indicator dot -->
                         <div id="status-dot" class="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="preview-link" target="_blank" class="hidden md:flex items-center gap-1.5 text-gray-500 hover:text-indigo-600 text-xs font-black uppercase tracking-wider px-3 py-2 rounded-xl transition-all hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Vista Previa
                </a>
                <button onclick="saveForm(true)" class="bg-indigo-600 text-white px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl shadow-indigo-200 hover:bg-indigo-700 active:scale-95 transition-all flex items-center gap-2">
                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Guardar
                </button>
            </div>
        </div>

        <!-- Canvas -->
        <div class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-10 custom-scrollbar relative" id="builder-canvas-container">
            <!-- Simulated Paper -->
            <div onclick="openPropertyEditor(-1)" class="max-w-md md:max-w-3xl mx-auto bg-white rounded-[2rem] shadow-2xl shadow-indigo-100/50 border border-gray-100 min-h-[600px] flex flex-col md:scale-100 transition-transform origin-top overflow-hidden cursor-pointer">
                
                <!-- Form Header Preview -->
                <div class="p-8 md:p-12 text-center border-b border-gray-50 pointer-events-none select-none bg-gradient-to-br from-indigo-50/10 via-white to-transparent">
                    <div id="preview-logo" class="w-20 h-20 bg-white shadow-xl shadow-indigo-100/50 rounded-3xl mx-auto mb-6 flex items-center justify-center text-indigo-400 transform rotate-3">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h2 id="preview-title" class="text-3xl font-black text-gray-900 tracking-tight leading-tight mb-2">Título del Formulario</h2>
                    <p id="preview-desc" class="text-base text-gray-400 font-medium max-w-sm mx-auto">Una breve descripción que describe el propósito de este formulario...</p>
                </div>

                <!-- Fields List (Sortable Grid) -->
                <div id="fields-list" class="flex flex-wrap -mx-2 p-8 md:p-12 min-h-full">
                    <!-- Fields are injected here -->
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="hidden flex flex-col items-center justify-center flex-1 text-center py-20 px-10">
                    <div class="w-24 h-24 bg-indigo-50 rounded-[2rem] flex items-center justify-center text-indigo-300 mb-6 animate-pulse">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-gray-800 mb-2 uppercase tracking-wide">Comienza tu diseño</h3>
                    <p class="text-gray-400 text-sm max-w-xs leading-relaxed">Arrastra componentes o usa el panel lateral para añadir los primeros campos de tu formulario.</p>
                </div>
            </div>
            
            <div class="h-24 md:hidden"></div> <!-- Spacer for mobile FAB -->
        </div>

        <!-- FAB (Add Field) - MOBILE ONLY -->
        <button onclick="openDrawer('add-field-drawer')" class="md:hidden absolute bottom-6 right-6 w-16 h-16 bg-indigo-600 rounded-3xl text-white shadow-2xl shadow-indigo-400 flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-20">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        </button>
    </main>

    <!-- RIGHT SIDEBAR: PROPERTIES (DESKTOP) & DRAWER (MOBILE) -->
    <aside id="properties-panel" class="hidden md:flex flex-col w-80 bg-white border-l border-gray-200 shadow-sm shrink-0 z-20 transition-all duration-300 translate-x-full md:translate-x-0 overflow-y-auto custom-scrollbar">
        <!-- Default State (No Selection) -->
        <div id="prop-empty" class="hidden flex flex-col items-center justify-center h-full text-center p-8 text-gray-400">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-300">
                 <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </div>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">Configuración</p>
            <p class="text-xs mt-2 leading-relaxed">Selecciona un elemento en el lienzo central para personalizar sus opciones y validaciones.</p>
        </div>

        <!-- Global Form Settings Editor -->
        <div id="global-settings-editor" class="flex flex-col h-full bg-white">
            <div class="p-6 border-b border-gray-100 sticky top-0 bg-white z-10">
                <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest">Configuración Global</h3>
            </div>
            <div class="p-6 space-y-6 flex-1 overflow-y-auto custom-scrollbar">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Nombre del Formulario</label>
                    <input type="text" id="global-title" oninput="saveGlobalProperties()" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Descripción</label>
                    <textarea id="global-desc" oninput="saveGlobalProperties()" rows="3" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all"></textarea>
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Texto Botón Enviar</label>
                    <input type="text" id="global-submit-label" oninput="saveGlobalProperties()" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all" placeholder="Enviar Formulario">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Mensaje de Agradecimiento</label>
                    <textarea id="global-success-msg" oninput="saveGlobalProperties()" rows="4" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all" placeholder="¡Gracias! Tu formulario ha sido enviado."></textarea>
                    <p class="text-[9px] text-gray-400 mt-2 font-bold uppercase tracking-wider leading-relaxed">Puedes usar shortcodes como {{FORM_NAME}} o {{nombre_campo}}.</p>
                </div>
                
                <div class="h-px bg-gray-50"></div>

                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Encabezado Visual</label>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 cursor-pointer select-none" onclick="toggleCheckbox('global-show-header')">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-gray-700 uppercase tracking-wide">Mostrar Encabezado</span>
                            <span class="text-[10px] text-gray-400">Título, descripción e icono</span>
                        </div>
                        <input type="checkbox" id="global-show-header" onchange="saveGlobalProperties()" class="w-6 h-6 text-indigo-600 rounded-lg focus:ring-indigo-500 border-gray-300 pointer-events-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">URL Imagen / Logo</label>
                    <input type="text" id="global-hero" oninput="saveGlobalProperties()" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all" placeholder="URL de la imagen o icono">
                </div>
            </div>
        </div>

        <!-- Editor State: Field -->
        <div id="prop-editor" class="hidden flex flex-col h-full bg-white">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
                <div class="flex flex-col">
                    <span id="prop-type-tag" class="text-[9px] font-black text-indigo-500 uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded-full inline-block max-w-fit mb-1">Tipo de Campo</span>
                    <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest">Ajustes</h3>
                </div>
                <button onclick="deleteCurrentField()" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-xl transition-all" title="Eliminar Campo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-6 flex-1">
                <!-- Group: Diseño -->
                <div id="prop-layout-wrapper" class="space-y-4">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Disposición</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button onclick="setFieldWidth('100')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="100">Full</button>
                        <button onclick="setFieldWidth('50')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="50">1/2</button>
                        <button onclick="setFieldWidth('33')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="33">1/3</button>
                        <button onclick="setFieldWidth('25')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="25">1/4</button>
                        <button onclick="setFieldWidth('66')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="66">2/3</button>
                        <button onclick="setFieldWidth('75')" class="prop-width-btn py-2 text-[10px] font-bold rounded-lg border border-gray-100 bg-gray-50 hover:bg-white truncate" data-width="75">3/4</button>
                    </div>
                </div>

                <!-- Group: Básico -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Icono o Imagen</label>
                        <div class="space-y-3">
                            <div class="flex p-1 bg-gray-50 rounded-xl">
                                <button onclick="setFieldIconType('icon')" id="btn-icon-type-icon" class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all">Icono</button>
                                <button onclick="setFieldIconType('image')" id="btn-icon-type-image" class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all">Imagen</button>
                            </div>
                            
                            <div id="prop-icon-picker-wrapper" class="flex gap-2">
                                <div id="prop-icon-preview" class="w-12 h-12 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center text-indigo-500 shrink-0"></div>
                                <button onclick="openIconPicker()" class="flex-1 bg-white border border-gray-200 rounded-xl px-4 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 transition-all">Elegir Icono</button>
                                <input type="hidden" id="prop-icon">
                            </div>

                            <div id="prop-icon-url-wrapper" class="hidden">
                                <div class="flex gap-2 mb-2">
                                    <input type="text" id="prop-icon-url" oninput="saveFieldProperties(true)" class="flex-1 bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm" placeholder="URL de la imagen...">
                                    <button onclick="document.getElementById('prop-icon-file').click()" class="bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl px-3 py-2 transition-all" title="Subir Imagen">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    </button>
                                    <input type="file" id="prop-icon-file" class="hidden" accept="image/*" onchange="uploadIcon(this)">
                                </div>
                                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Pega un enlace o sube una imagen.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Etiqueta Principal</label>
                        <input type="text" id="prop-label" oninput="saveFieldProperties(true)" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                    </div>
                    <div id="prop-placeholder-wrapper">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Instrucciones / Placeholder</label>
                        <input type="text" id="prop-placeholder" oninput="saveFieldProperties(true)" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm">
                    </div>
                </div>

                <!-- Divider -->
                <div class="h-px bg-gray-50"></div>

                <!-- Group: Reglas -->
                <div class="space-y-4">
                     <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Validación</label>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 cursor-pointer select-none" onclick="toggleCheckbox('prop-required')">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-gray-700 uppercase tracking-wide">Obligatorio</span>
                            <span class="text-[10px] text-gray-400">El usuario debe completar este campo</span>
                        </div>
                        <input type="checkbox" id="prop-required" onchange="saveFieldProperties(true)" class="w-6 h-6 text-indigo-600 rounded-lg focus:ring-indigo-500 border-gray-300 transition-all pointer-events-none">
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 cursor-pointer select-none" onclick="toggleCheckbox('prop-hidden')">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-gray-700 uppercase tracking-wide">Campo Oculto</span>
                            <span class="text-[10px] text-gray-400">Invisible en el formulario público</span>
                        </div>
                        <input type="checkbox" id="prop-hidden" onchange="saveFieldProperties(true)" class="w-6 h-6 text-indigo-600 rounded-lg focus:ring-indigo-500 border-gray-300 transition-all pointer-events-none">
                    </div>
                </div>

                <!-- Group: Smart Field Config -->
                <div id="prop-smart-wrapper" class="hidden space-y-4 pt-4 border-t border-gray-100">
                     <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Fuente de Datos</label>
                     <select id="prop-smart-source" onchange="saveFieldProperties(true)" class="w-full bg-purple-50 border border-purple-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-purple-500 text-purple-700">
                         <option value="quote_id">ID de Cotización</option>
                         <option value="client_name">Nombre del Cliente</option>
                         <option value="client_email">Email del Cliente</option>
                         <option value="client_phone">Teléfono del Cliente</option>
                         <option value="total_amount">Monto Total</option>
                         <option value="public_link">Link de Cotización</option>
                     </select>
                     <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider leading-relaxed">Este campo se autocompletará con datos de la cotización vinculada.</p>
                </div>

                <!-- Group: Notificaciones (Phone/Email) -->
                <div id="prop-notif-wrapper" class="hidden space-y-4 pt-4 border-t border-gray-100">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Notificaciones Automáticas</label>
                    
                    <div class="flex items-center justify-between p-4 bg-blue-50/50 rounded-2xl border border-blue-100 cursor-pointer select-none" onclick="toggleCheckbox('prop-notif-enabled')">
                        <div class="flex flex-col">
                            <span id="prop-notif-title" class="text-xs font-black text-blue-700 uppercase tracking-wide">Enviar WhatsApp</span>
                            <span class="text-[10px] text-blue-500/70">Al recibir una respuesta</span>
                        </div>
                        <input type="checkbox" id="prop-notif-enabled" onchange="saveFieldProperties(true)" class="w-6 h-6 text-blue-600 rounded-lg focus:ring-blue-500 border-blue-300 transition-all pointer-events-none">
                    </div>
                    
                    <div id="prop-notif-content" class="hidden space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Mensaje Personalizado</label>
                            <textarea id="prop-notif-msg" oninput="saveFieldProperties(true)" rows="4" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all shadow-sm" placeholder="Ej: Hola {{nombre}}, gracias por contactarnos..."></textarea>
                            <p class="text-[9px] text-gray-400 mt-2 font-bold uppercase tracking-wider leading-relaxed">Usa {{Nombre del Campo}} para insertar datos dinámicos.</p>
                        </div>
                    </div>
                </div>



                <!-- Group: Productos -->
                <div id="prop-product-wrapper" class="hidden space-y-4 pt-4 border-t border-gray-100">
                     <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Configuración de Producto</label>
                     <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 cursor-pointer select-none" onclick="toggleCheckbox('prop-prod-show-price')">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-gray-700 uppercase tracking-wide">Mostrar Precio</span>
                            <span class="text-[10px] text-gray-400">Ver valor en el formulario</span>
                        </div>
                        <input type="checkbox" id="prop-prod-show-price" onchange="saveFieldProperties(true)" class="w-6 h-6 text-indigo-600 rounded-lg focus:ring-indigo-500 border-gray-300 pointer-events-none">
                    </div>
                </div>

                <!-- Group: File Configuration -->
                <div id="prop-file-wrapper" class="hidden space-y-4 pt-4 border-t border-gray-100">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Configuración de Archivo</label>
                    <div>
                        <label class="block text-[9px] font-black text-gray-400 uppercase mb-2 tracking-widest">Extensiones Permitidas</label>
                        <input type="text" id="prop-file-ext" oninput="saveFieldProperties(true)" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="pdf, jpg, png, docx">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black text-gray-400 uppercase mb-2 tracking-widest">Tamaño Máximo (MB)</label>
                        <input type="number" id="prop-file-size" oninput="saveFieldProperties(true)" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="5">
                    </div>
                </div>

                <!-- Group: Consent Configuration -->
                <div id="prop-consent-wrapper" class="hidden space-y-4 pt-4 border-t border-gray-100">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Configuración de Consentimiento</label>
                    <div>
                        <label class="block text-[9px] font-black text-gray-400 uppercase mb-2 tracking-widest">Texto de Enlace / Términos (Opcional)</label>
                        <textarea id="prop-consent-html" oninput="saveFieldProperties(true)" rows="3" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ver <a href='...' target='_blank'>Términos y Condiciones</a>"></textarea>
                    </div>
                </div>

                <!-- Group: Opciones dinámicas -->
                <div id="prop-options-wrapper" class="hidden animate-in fade-in slide-in-from-top-2 duration-300">
                    <div class="h-px bg-gray-50 my-6"></div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest">Opciones Disponibles</label>
                    <textarea id="prop-options" rows="4" oninput="saveFieldProperties(true)" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all shadow-sm" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"></textarea>
                    <p class="text-[9px] text-gray-400 mt-2 font-bold uppercase tracking-wider leading-relaxed">Coloca cada opción en una línea distinta.</p>
                </div>

                <!-- Group: Lógica Condicional Avanzada (Gravity Style) -->
                <div id="prop-logic-wrapper" class="space-y-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                         <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Lógica Condicional</label>
                         <button onclick="toggleLogic()" id="btn-toggle-logic" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Habilitar</button>
                    </div>

                    <div id="logic-builder-container" class="hidden space-y-4">
                        <div class="flex items-center gap-2 text-xs text-gray-600">
                            <span>Mostrar este campo si</span>
                            <select id="logic-match-type" onchange="saveFieldProperties(true)" class="bg-gray-100 border-none rounded-lg px-2 py-1 font-bold text-indigo-600 focus:ring-0 cursor-pointer">
                                <option value="all">TODAS</option>
                                <option value="any">CUALQUIERA</option>
                            </select>
                            <span>de las siguientes coinciden:</span>
                        </div>

                        <div id="logic-rules-list" class="space-y-2">
                            <!-- Rules Injected Here -->
                        </div>

                        <button onclick="addLogicRule()" class="flex items-center gap-1 text-[10px] font-black text-indigo-500 uppercase tracking-widest hover:bg-indigo-50 px-3 py-2 rounded-lg transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Añadir Regla
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="p-6 border-t border-gray-50 bg-white md:hidden"> 
                 <button onclick="closeDrawer('edit-field-drawer')" class="w-full bg-indigo-600 text-white font-black uppercase tracking-widest py-4 rounded-2xl hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all">Listo</button>
            </div>
        </div>
    </aside>
</div>

<!-- ICON PICKER MODAL -->
<div id="icon-picker-modal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeIconPicker()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-3xl shadow-2xl p-8 transform transition-all scale-100">
        <h3 class="text-xl font-black text-gray-900 mb-6 uppercase tracking-tight">Seleccionar Icono</h3>
        <div class="grid grid-cols-5 gap-3 max-h-[400px] overflow-y-auto p-1 custom-scrollbar" id="icon-grid">
            <!-- Icons will be injected here -->
        </div>
        <div class="mt-8 flex justify-end">
            <button onclick="closeIconPicker()" class="px-6 py-3 bg-gray-100 text-gray-700 font-black uppercase tracking-widest rounded-xl hover:bg-gray-200 transition-all">Cancelar</button>
        </div>
    </div>
</div>

<!-- MOBILE DRAWERS (For < md screens) -->
<div id="add-field-drawer" class="md:hidden fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" onclick="closeDrawer('add-field-drawer')"></div>
    <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[2.5rem] p-8 shadow-2xl transform transition-transform translate-y-full max-h-[85vh] overflow-y-auto">
        <div class="w-12 h-1.5 bg-gray-100 rounded-full mx-auto mb-8"></div>
        <h3 class="text-xl font-black text-gray-900 mb-6 uppercase tracking-wide">Añadir Componente</h3>
        
        <div class="grid grid-cols-3 gap-3">
             <button onclick="addField('text')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Texto</span>
            </button>
             <button onclick="addField('full_name')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Nombre</span>
            </button>
             <button onclick="addField('step')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-indigo-50 hover:bg-indigo-100 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-indigo-500 group-hover:text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                </div>
                <span class="text-[9px] font-black text-indigo-700 uppercase tracking-wider">Paso</span>
            </button>
            <button onclick="addField('email')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Email</span>
            </button>
            <button onclick="addField('file')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Archivo</span>
            </button>
            <button onclick="addField('product')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Producto</span>
            </button>
            <button onclick="addField('consent')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Consent.</span>
            </button>
            <button onclick="addField('date')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Fecha</span>
            </button>
            <button onclick="addField('time_range')" class="flex flex-col items-center gap-2 p-4 rounded-2xl bg-gray-50 hover:bg-indigo-50 transition-all active:scale-95 group">
                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-400 group-hover:text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="text-[9px] font-black text-gray-700 uppercase tracking-wider">Franja H.</span>
            </button>
            <!-- More components can be added here if needed -->
        </div>
        <div class="mt-8">
             <p class="text-[10px] text-center text-gray-300 font-bold uppercase tracking-widest">Arrastra para reordenar desde el lienzo</p>
        </div>
    </div>
</div>

<!-- Mobile Edit Drawer -->
<div id="edit-field-drawer" class="md:hidden fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" onclick="closeDrawer('edit-field-drawer')"></div>
    <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[2.5rem] p-0 shadow-2xl transform transition-transform translate-y-full max-h-[90vh] overflow-y-auto" id="mobile-prop-container">
        <!-- JS will move #prop-editor here on mobile -->
    </div>
</div>


<script>
function saveGlobalProperties() {
    formData.titulo = document.getElementById('global-title').value;
    formData.descripcion = document.getElementById('global-desc').value;
    if(!formData.config) formData.config = {};
    formData.config.show_header = document.getElementById('global-show-header').checked;
    formData.config.hero_image = document.getElementById('global-hero').value;
    formData.submit_label = document.getElementById('global-submit-label').value;
    formData.success_message = document.getElementById('global-success-msg').value;
    
    updateGlobalPreview();
    updateSaveStatus('Cambios sin guardar');
}

function updateGlobalPreview() {
    document.getElementById('form-title').innerText = formData.titulo || 'Cargando...';
    document.getElementById('preview-title').innerText = formData.titulo || 'Título del Formulario';
    document.getElementById('preview-desc').innerText = formData.descripcion || 'Una breve descripción que describe el propósito de este formulario...';
    
    const showHeader = formData.config?.show_header !== false;
    const heroImage = formData.config?.hero_image || '';
    
    const logoDiv = document.getElementById('preview-logo');
    if(heroImage) {
        logoDiv.innerHTML = `<img src="${heroImage}" class="w-full h-full object-cover">`;
    } else {
        logoDiv.innerHTML = `<svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
    }
}


function setFieldWidth(width) {
    if(currentFieldIdx === -1) return;
    const field = formData.fields[currentFieldIdx];
    if(!field.settings) field.settings = {};
    field.settings.width = width;
    
    // Update active button UI
    document.querySelectorAll('.prop-width-btn').forEach(btn => {
        btn.classList.toggle('bg-indigo-600', btn.dataset.width === width);
        btn.classList.toggle('text-white', btn.dataset.width === width);
        btn.classList.toggle('border-indigo-600', btn.dataset.width === width);
    });
    
    renderFields();
    updateSaveStatus('Cambios sin guardar');
}

function setFieldIconType(type) {
    if(currentFieldIdx === -1) return;
    const field = formData.fields[currentFieldIdx];
    if(!field.settings) field.settings = {};
    field.settings.icon_type = type;
    
    updateIconUI();
    saveFieldProperties(true);
}

function updateIconUI() {
    if(currentFieldIdx === -1) return;
    const field = formData.fields[currentFieldIdx];
    const iconType = field.settings?.icon_type || 'icon';
    
    document.getElementById('prop-icon-picker-wrapper').classList.toggle('hidden', iconType !== 'icon');
    document.getElementById('prop-icon-url-wrapper').classList.toggle('hidden', iconType !== 'image');
    
    const btnIcon = document.getElementById('btn-icon-type-icon');
    const btnImage = document.getElementById('btn-icon-type-image');
    
    btnIcon.className = `flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all ${iconType === 'icon' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-100'}`;
    btnImage.className = `flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all ${iconType === 'image' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-100'}`;
    
    // Preview
    const preview = document.getElementById('prop-icon-preview');
    preview.innerHTML = getIconSvgByName(field.icon || getIconNameForType(field.type));
}

function openIconPicker() {
    const modal = document.getElementById('icon-picker-modal');
    modal.classList.remove('hidden');
    
    const grid = document.getElementById('icon-grid');
    grid.innerHTML = '';
    
    const icons = ['user', 'document-text', 'hashtag', 'envelope', 'device-mobile', 'map-pin', 'calendar', 'clock', 'shopping-bag', 'shield-check', 'pencil', 'paper-clip', 'photograph', 'star', 'heart', 'bell', 'search', 'cog', 'trash', 'check-circle'];
    
    icons.forEach(icon => {
        const btn = document.createElement('button');
        btn.className = 'w-full aspect-square bg-gray-50 hover:bg-indigo-600 hover:text-white rounded-2xl flex items-center justify-center transition-all group';
        btn.innerHTML = getIconSvgByName(icon);
        btn.onclick = () => {
            selectIcon(icon);
        };
        grid.appendChild(btn);
    });
}

function closeIconPicker() {
    document.getElementById('icon-picker-modal').classList.add('hidden');
}

function selectIcon(icon) {
    if(currentFieldIdx === -1) return;
    const field = formData.fields[currentFieldIdx];
    field.icon = icon;
    document.getElementById('prop-icon').value = icon;
    updateIconUI();
    saveFieldProperties(true);
    closeIconPicker();
}


function uploadIcon(input) {
    if (input.files && input.files[0]) {
        const formDataUpload = new FormData();
        formDataUpload.append('file', input.files[0]);
        // Also send request to same file if api_forms.php is external, but here we use api_forms.php
        
        updateSaveStatus('Subiendo...');
        
        fetch('api_forms.php?action=upload_icon', {
            method: 'POST',
            body: formDataUpload
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update URL input
                document.getElementById('prop-icon-url').value = data.url;
                
                // Update Field Logic
                if(currentFieldIdx !== -1) {
                    const field = formData.fields[currentFieldIdx];
                    if(!field.settings) field.settings = {};
                    field.settings.icon_type = 'image';
                    field.icon = data.url;
                    
                    // Force update UI
                    document.getElementById('btn-icon-type-image').click(); 
                    saveFieldProperties(true);
                }
                updateSaveStatus('Imagen subida');
            } else {
                alert('Error al subir imagen: ' + (data.error || 'Desconocido'));
                updateSaveStatus('Error de subida');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            updateSaveStatus('Error de conexión');
        });
    }
}

let formData = { fields: [] };
let currentFieldIdx = -1;
const formId = <?php echo $id; ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadForm();
    initSortable();
});

function initSortable() {
    new Sortable(document.getElementById('fields-list'), {
        animation: 250,
        handle: '.drag-handle', // Handle only
        ghostClass: 'opacity-20',
        dragClass: 'opacity-100',
        onEnd: function (evt) {
            if(evt.oldIndex === evt.newIndex) return;
            // Reorder array
            const item = formData.fields.splice(evt.oldIndex, 1)[0];
            formData.fields.splice(evt.newIndex, 0, item);
            
            // Critical: Re-render fields to update indices in onclick handlers
            renderFields();
            
            // If the moved field was selected, keep it selected (update index)
            if(currentFieldIdx === evt.oldIndex) {
                currentFieldIdx = evt.newIndex;
            } else if (currentFieldIdx > evt.oldIndex && currentFieldIdx <= evt.newIndex) {
                 currentFieldIdx--;
            } else if (currentFieldIdx < evt.oldIndex && currentFieldIdx >= evt.newIndex) {
                 currentFieldIdx++;
            }
            
            // Refresh property editor if open
            if(currentFieldIdx !== -1) openPropertyEditor(currentFieldIdx);
            
            updateSaveStatus('Cambios sin guardar');
        }
    });
}

function loadForm() {
    fetch(`api_forms.php?action=get&id=${formId}`)
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                formData = res.data;
                if(!formData.fields) formData.fields = [];
                
                // Ensure correct types and properties
                formData.fields = formData.fields.map((f, i) => ({
                    ...f,
                    name: f.name || 'field_' + i,
                    icon: f.icon || getIconNameForType(f.type),
                    settings: typeof f.settings === 'string' ? JSON.parse(f.settings || '{}') : (f.settings || {}),
                    validation: typeof f.validation === 'string' ? JSON.parse(f.validation || '{}') : (f.validation || {}),
                    options: typeof f.options === 'string' ? JSON.parse(f.options || '[]') : (f.options || []),
                    visibility_rules: typeof f.visibility_rules === 'string' ? JSON.parse(f.visibility_rules || '{}') : (f.visibility_rules || {})
                }));

                // UI Updates
                document.getElementById('form-title').innerText = formData.titulo;
                document.getElementById('preview-title').innerText = formData.titulo;
                document.getElementById('preview-desc').innerText = formData.descripcion || 'Formulario dinámico';
                
                // Load Global Settings
                if(!formData.config) formData.config = { show_header: true };
                else if(typeof formData.config === 'string') formData.config = JSON.parse(formData.config || '{}');
                
                document.getElementById('global-title').value = formData.titulo || '';
                document.getElementById('global-desc').value = formData.descripcion || '';
                document.getElementById('global-show-header').checked = formData.config.show_header !== false;
                document.getElementById('global-hero').value = formData.config.hero_image || '';
                document.getElementById('global-submit-label').value = formData.submit_label || 'Enviar Formulario';
                document.getElementById('global-success-msg').value = formData.success_message || '¡Gracias! Tu formulario ha sido enviado.';

                updateGlobalPreview();
                
                if(formData.hash_publico) {
                    const previewUrl = `public_form.php?hash=${formData.hash_publico}`;
                    document.getElementById('preview-link').href = previewUrl;
                    document.getElementById('preview-link').classList.remove('hidden');
                }
                
                renderFields();
            } else {
                alert('Error cargando formulario');
            }
        });
}

function renderFields() {
    const list = document.getElementById('fields-list');
    const emptyState = document.getElementById('empty-state');
    
    list.innerHTML = '';
    
    if(formData.fields.length === 0) {
        emptyState.classList.remove('hidden');
        return;
    }
    emptyState.classList.add('hidden');

    formData.fields.forEach((field, idx) => {
        const width = field.settings?.width || '100';
        let widthClass = 'w-full px-2 mb-6';
        if(width == '50') widthClass = 'w-1/2 px-2 mb-6';
        else if(width == '33') widthClass = 'w-1/3 px-2 mb-6';
        else if(width == '25') widthClass = 'w-1/4 px-2 mb-6';
        else if(width == '66') widthClass = 'w-2/3 px-2 mb-6';
        else if(width == '75') widthClass = 'w-3/4 px-2 mb-6';
        else if(width == '20') widthClass = 'w-1/5 px-2 mb-6';

        const div = document.createElement('div');
        div.className = `field-wrapper ${widthClass}`;
        
        const isSelected = idx === currentFieldIdx;
        
        let innerHtml = '';
        if(field.type === 'step') {
            innerHtml = `
                <div class="group relative p-4 rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-100 border-none flex items-center gap-4 ${isSelected ? 'ring-4 ring-indigo-500/30' : ''}">
                    <div class="drag-handle cursor-move opacity-50 hover:opacity-100 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-70">Paso del Navegador</p>
                        <h4 class="text-sm font-black uppercase tracking-widest truncate">${field.label || 'Título del Paso'}</h4>
                    </div>
                    <div class="text-indigo-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                    </div>
                </div>
            `;
        } else if(field.type === 'section') {
            innerHtml = `
                <div class="group relative p-4 rounded-2xl bg-gray-50 border-l-4 border-indigo-500 text-gray-800 flex items-center gap-4 ${isSelected ? 'ring-4 ring-indigo-500/10' : ''}">
                    <div class="drag-handle cursor-move opacity-50 hover:opacity-100 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-black uppercase tracking-widest italic text-indigo-600">${field.label || 'Separador de Sección'}</p>
                    </div>
                </div>
            `;
        } else {
            // Icon or Image Preview
            let mediaHtml = '';
            if(field.settings?.icon_type === 'image' && field.icon) {
                mediaHtml = `<img src="${field.icon}" class="w-full h-full object-cover">`;
            } else {
                mediaHtml = getIconSvgByName(field.icon || getIconNameForType(field.type));
            }

            innerHtml = `
                <div class="group relative p-6 h-full rounded-3xl border transition-all cursor-pointer flex items-center gap-5 ${isSelected ? 'bg-indigo-50 border-indigo-400 ring-4 ring-indigo-500/5' : 'bg-white border-gray-100 hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-100/30'}">
                    <div class="drag-handle cursor-move text-gray-300 hover:text-indigo-400 p-2 -ml-2 rounded-xl transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                    </div>
                    <div class="w-14 h-14 rounded-2xl overflow-hidden ${isSelected ? 'bg-white text-indigo-600 shadow-lg shadow-indigo-100' : 'bg-gray-50 text-gray-400'} flex items-center justify-center shrink-0 transition-all group-hover:bg-indigo-600 group-hover:text-white group-hover:rotate-3">
                        ${mediaHtml}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-[0.15em] text-indigo-400 mb-0.5">${field.type}</p>
                        <p class="text-sm font-black ${isSelected ? 'text-indigo-900' : 'text-gray-800'} truncate uppercase tracking-wide tracking-tight leading-none">${field.label || 'Nuevo Campo'}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                             ${field.validation?.required ? '<span class="text-[8px] font-black uppercase tracking-widest text-red-500 bg-red-50 px-1.5 py-0.5 rounded-full border border-red-100">Obligatorio</span>' : ''}
                             ${field.options?.length > 0 ? `<span class="text-[8px] font-black uppercase tracking-widest text-indigo-400 bg-indigo-50 px-1.5 py-0.5 rounded-full border border-indigo-100">${field.options.length} Opciones</span>` : ''}
                             ${field.options?.length > 0 ? `<span class="text-[8px] font-black uppercase tracking-widest text-indigo-400 bg-indigo-50 px-1.5 py-0.5 rounded-full border border-indigo-100">${field.options.length} Opciones</span>` : ''}
                             ${field.visibility_rules?.enabled ? `<span class="text-[8px] font-black uppercase tracking-widest text-amber-500 bg-amber-50 px-1.5 py-0.5 rounded-full border border-amber-100">Lógica</span>` : ''}
                             ${field.settings?.is_hidden ? `<span class="text-[8px] font-black uppercase tracking-widest text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded-full border border-gray-200">Oculto</span>` : ''}
                             ${field.type === 'smart_field' ? `<span class="text-[8px] font-black uppercase tracking-widest text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded-full border border-purple-100">Smart: ${field.settings?.smart_source || 'N/A'}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        div.innerHTML = innerHtml;
        div.onclick = (e) => { 
            if(!e.target.closest('.drag-handle') && !e.target.closest('button')) {
                e.stopPropagation(); // Prevent bubbling to parent container
                openPropertyEditor(idx);
            }
        };
        list.appendChild(div);
    });
}

function addField(type) {
    const newField = {
        type: type,
        label: getDefaultLabelForType(type),
        name: 'f_' + Math.random().toString(36).substr(2, 9),
        placeholder: '',
        icon: getIconNameForType(type),
        validation: { required: false },
        options: [],
        settings: {}
    };
    
    formData.fields.push(newField);
    renderFields();
    closeDrawer('add-field-drawer');
    openPropertyEditor(formData.fields.length - 1);
    updateSaveStatus('Cambios sin guardar');
}

function openPropertyEditor(idx) {
    try {
        // Global logic
        if (idx === -1) {
            document.getElementById('prop-editor').classList.add('hidden');
            document.getElementById('global-settings-editor').classList.remove('hidden');
            document.getElementById('prop-empty').classList.add('hidden');
            // Unselect all fields visually
            currentFieldIdx = -1;
            renderFields();
            return;
        }

        currentFieldIdx = idx;
        const field = formData.fields[idx];
        if (!field) return;

        const isMobile = window.innerWidth < 768;
        
        // Update Property Fields
        document.getElementById('prop-type-tag').innerText = field.type;
        document.getElementById('prop-label').value = field.label;
        document.getElementById('prop-placeholder').value = field.placeholder || '';
    document.getElementById('prop-required').checked = field.validation?.required || false;
    document.getElementById('prop-hidden').checked = field.settings?.is_hidden || false;
    
    // Icon UI
    updateIconUI();
    document.getElementById('prop-icon-url').value = (field.settings?.icon_type === 'image' ? field.icon : '') || '';
    
    // UI Logic per type
    const optsWrapper = document.getElementById('prop-options-wrapper');
    const placeholdersWrapper = document.getElementById('prop-placeholder-wrapper');
    const productsWrapper = document.getElementById('prop-product-wrapper');

    // Show/Hide Options
    if(['select', 'radio', 'checkbox'].includes(field.type)) {
        optsWrapper.classList.remove('hidden');
        document.getElementById('prop-options').value = (field.options || []).join('\n');
    } else {
        optsWrapper.classList.add('hidden');
    }

    // Hide placeholder for step/section/image
    if(['step', 'section', 'image', 'title', 'html'].includes(field.type)) {
        placeholdersWrapper.classList.add('hidden');
    } else {
        placeholdersWrapper.classList.remove('hidden');
    }

    // Products
    if(field.type === 'product') {
        productsWrapper.classList.remove('hidden');
    } else {
        productsWrapper.classList.add('hidden');
    }

    // Notifications (Phone/Email)
    const notifWrapper = document.getElementById('prop-notif-wrapper');
    if(['phone', 'email'].includes(field.type)) {
        notifWrapper.classList.remove('hidden');
        const isEnabled = field.settings?.notifications?.enabled || false;
        document.getElementById('prop-notif-enabled').checked = isEnabled;
        document.getElementById('prop-notif-msg').value = field.settings?.notifications?.message || '';
        document.getElementById('prop-notif-content').classList.toggle('hidden', !isEnabled);
        document.getElementById('prop-notif-title').innerText = field.type === 'phone' ? 'Enviar WhatsApp' : 'Enviar Email';
    } else {
        notifWrapper.classList.add('hidden');
    }

    // File Configuration
    const fileWrapper = document.getElementById('prop-file-wrapper');
    if(field.type === 'file') {
        fileWrapper.classList.remove('hidden');
        document.getElementById('prop-file-ext').value = field.settings?.allowed_extensions || 'pdf, jpg, png, docx';
        document.getElementById('prop-file-size').value = field.settings?.max_size_mb || '5';
    } else {
        fileWrapper.classList.add('hidden');
    }

    // Consent Configuration
    const consentWrapper = document.getElementById('prop-consent-wrapper');
    if(field.type === 'consent') {
        consentWrapper.classList.remove('hidden');
        document.getElementById('prop-consent-html').value = field.settings?.consent_html || '';
    } else {
        consentWrapper.classList.add('hidden');
    }

    // Product Configuration
    if(field.type === 'product') {
        document.getElementById('prop-prod-show-price').checked = field.settings?.show_price !== false;
    }

    // Smart Field Configuration
    const smartWrapper = document.getElementById('prop-smart-wrapper');
    if(field.type === 'smart_field') {
        smartWrapper.classList.remove('hidden');
        document.getElementById('prop-smart-source').value = field.settings?.smart_source || 'quote_id';
    } else {
        smartWrapper.classList.add('hidden');
    }

    // Conditional Logic (Advanced)
    const logicWrapper = document.getElementById('prop-logic-wrapper');
    const builderContainer = document.getElementById('logic-builder-container');
    const toggleBtn = document.getElementById('btn-toggle-logic');

    if(field.type === 'step' || idx === 0) {
        logicWrapper.classList.add('hidden');
    } else {
        logicWrapper.classList.remove('hidden');
        
        const logic = field.visibility_rules || { enabled: false, match: 'all', rules: [] };
        
        // Migrate old logic if exists
        if(logic.field_idx !== undefined) {
             logic.enabled = true;
             logic.match = 'all';
             logic.rules = [{ field: logic.field_idx, op: logic.operator, value: logic.value }];
             delete logic.field_idx;
             delete logic.operator;
             delete logic.value;
             // Save migration immediately
             field.visibility_rules = logic;
        }

        if(logic.enabled) {
            builderContainer.classList.remove('hidden');
            toggleBtn.innerText = 'Deshabilitar';
            toggleBtn.classList.replace('text-indigo-600', 'text-red-500');
            document.getElementById('logic-match-type').value = logic.match;
            renderLogicRules(logic.rules, idx);
        } else {
            builderContainer.classList.add('hidden');
            toggleBtn.innerText = 'Habilitar';
            toggleBtn.classList.replace('text-red-500', 'text-indigo-600');
        }
    }

    // Layout Sync
    const width = field.settings?.width || '100';
    document.querySelectorAll('.prop-width-btn').forEach(btn => {
        btn.classList.toggle('bg-indigo-600', btn.dataset.width === width);
        btn.classList.toggle('text-white', btn.dataset.width === width);
        btn.classList.toggle('border-indigo-600', btn.dataset.width === width);
    });

    renderFields(); // Refresh selection visual

    // Fix: Ensure we don't hide the editor if it was moved to mobile container
    if(isMobile) {
        const editor = idx === -1 ? document.getElementById('global-settings-editor') : document.getElementById('prop-editor');
        const uniqueId = `mobile-prop-container-${idx === -1 ? 'global' : 'field'}`;
        // Verify if already appended
        if(document.getElementById('mobile-prop-container').firstElementChild !== editor) {
             document.getElementById('mobile-prop-container').innerHTML = '';
             document.getElementById('mobile-prop-container').appendChild(editor);
        }
        editor.classList.remove('hidden');
        openDrawer('edit-field-drawer');
    } else {
        // Desktop: Ensure editors are back in the sidebar if they were moved
        const propPanel = document.getElementById('properties-panel');
        const propEditor = document.getElementById('prop-editor');
        const globalEditor = document.getElementById('global-settings-editor');
        const emptyState = document.getElementById('prop-empty');

        if(propPanel) {
             if(propEditor.parentElement !== propPanel) propPanel.appendChild(propEditor);
             if(globalEditor.parentElement !== propPanel) propPanel.appendChild(globalEditor);
             if(emptyState.parentElement !== propPanel) propPanel.appendChild(emptyState);
        }

        if(idx === -1) {
            globalEditor.classList.remove('hidden');
            propEditor.classList.add('hidden');
        } else {
            globalEditor.classList.add('hidden');
            propEditor.classList.remove('hidden');
        }
        emptyState.classList.add('hidden');
    }
    } catch (e) {
        console.error('Error opening property editor:', e);
        alert('Error al abrir la configuración: ' + e.message);
    }
}

function saveFieldProperties(liveUpdate = false) {
    if(currentFieldIdx === -1) return;
    
    const field = formData.fields[currentFieldIdx];
    
    const iconType = field.settings?.icon_type || 'icon';
    if(iconType === 'image') {
        field.icon = document.getElementById('prop-icon-url').value;
    } else {
        field.icon = document.getElementById('prop-icon').value || field.icon;
    }

    field.label = document.getElementById('prop-label').value;
    field.placeholder = document.getElementById('prop-placeholder').value;
    field.validation.required = document.getElementById('prop-required').checked;
    
    if(!field.settings) field.settings = {};
    field.settings.is_hidden = document.getElementById('prop-hidden').checked;
    
    // Save Options
    if(['select', 'radio', 'checkbox'].includes(field.type)) {
        field.options = document.getElementById('prop-options').value.split('\n').map(o => o.trim()).filter(o => o !== '');
    }

    // Save Notifications
    if(['phone', 'email'].includes(field.type)) {
        const notifEnabled = document.getElementById('prop-notif-enabled').checked;
        if(!field.settings.notifications) field.settings.notifications = {};
        field.settings.notifications.enabled = notifEnabled;
        field.settings.notifications.message = document.getElementById('prop-notif-msg').value;
        document.getElementById('prop-notif-content').classList.toggle('hidden', !notifEnabled);
    }

    // Save File Settings
    if(field.type === 'file') {
        field.settings.allowed_extensions = document.getElementById('prop-file-ext').value;
        field.settings.max_size_mb = document.getElementById('prop-file-size').value;
    }

    // Save Consent Settings
    if(field.type === 'consent') {
        field.settings.consent_html = document.getElementById('prop-consent-html').value;
    }

    // Save Product Settings
    if(field.type === 'product') {
        field.settings.show_price = document.getElementById('prop-prod-show-price').checked;
    }

    // Save Smart Field Settings
    if(field.type === 'smart_field') {
        field.settings.smart_source = document.getElementById('prop-smart-source').value;
    }

    // Save Logic (Advanced)
    const isEnabled = !document.getElementById('logic-builder-container').classList.contains('hidden');
    if(isEnabled) {
        const rules = [];
        document.querySelectorAll('.logic-rule-row').forEach(row => {
            const fieldVal = row.querySelector('.rule-field').value;
            if(fieldVal !== '') {
                rules.push({
                    field: fieldVal, // Can be name (f_...) or index (legacy)
                    op: row.querySelector('.rule-op').value,
                    value: row.querySelector('.rule-value').value
                });
            }
        });
        
        field.visibility_rules = {
            enabled: true,
            match: document.getElementById('logic-match-type').value,
            rules: rules
        };
    } else {
        field.visibility_rules = { enabled: false, match: 'all', rules: [] };
    }
    
    renderFields();
    updateSaveStatus('Cambios sin guardar');
    if(!liveUpdate) closeDrawer('edit-field-drawer');
}

function deleteCurrentField() {
    if(currentFieldIdx === -1) return;
    if(confirm('¿Eliminar este componente del formulario?')) {
        formData.fields.splice(currentFieldIdx, 1);
        currentFieldIdx = -1;
        renderFields();
        document.getElementById('prop-editor').classList.add('hidden');
        document.getElementById('prop-empty').classList.remove('hidden');
        closeDrawer('edit-field-drawer');
        updateSaveStatus('Cambios sin guardar');
    }
}

function saveForm(notify = false) {
    updateSaveStatus('Guardando...');
    fetch('api_forms.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            updateSaveStatus('Guardado');
            if(notify) alert('Formulario guardado correctamente');
        } else {
            updateSaveStatus('Error');
            alert('Error: ' + res.error);
        }
    });
}

function updateSaveStatus(msg) {
    const status = document.getElementById('save-status');
    const dot = document.getElementById('status-dot');
    status.innerText = msg;
    
    if(msg === 'Guardado') {
        dot.className = 'w-1.5 h-1.5 rounded-full bg-green-500';
    } else if(msg.includes('Error')) {
        dot.className = 'w-1.5 h-1.5 rounded-full bg-red-500';
    } else {
        dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse';
    }
}

// Drawers
function openDrawer(id) {
    const el = document.getElementById(id);
    el.classList.remove('hidden');
    setTimeout(() => el.lastElementChild.classList.remove('translate-y-full'), 10);
}
function closeDrawer(id) {
    const el = document.getElementById(id);
    if(!el) return;
    el.lastElementChild.classList.add('translate-y-full');
    setTimeout(() => el.classList.add('hidden'), 300);
}

// Helpers
function toggleCheckbox(id) {
    const cb = document.getElementById(id);
    cb.checked = !cb.checked;
    saveFieldProperties(true);
}

function getDefaultLabelForType(type) {
    const labels = {
        text: 'Escribe algo aquí...',
        full_name: 'Nombres y Apellidos',
        textarea: 'Comentarios adicionales',
        number: 'Cantidad / Monto',
        email: 'Correo electrónico',
        url: 'Sitio Web / Perfil',
        date: 'Fecha de servicio',
        phone: 'Número de celular',
        address: 'Dirección de residencia',
        select: 'Selecciona una opción',
        radio: 'Marca una alternativa',
        checkbox: 'Selección múltiple',
        signature: 'Firma de conformidad',
        file: 'Adjuntar documento',
        step: 'Siguiente Paso',
        section: 'Nueva Sección',
        title: 'Título del bloque',
        image: 'Imagen informativa',
        product: 'Selección de Producto',
        captcha: 'Validación de seguridad',
        consent: 'Consentimiento de datos',
        document: 'NIT / Cédula / Identificación',
        time: 'Hora',
        date: 'Fecha de servicio',
        document: 'NIT / Cédula / Identificación',
        time: 'Hora',
        date: 'Fecha de servicio',
        time_range: 'Franja horaria preferida',
        smart_field: 'Dato Automático'
    };
    return labels[type] || 'Nuevo Campo';
}

function getIconNameForType(type) {
    const icons = {
        text: 'type-text', full_name: 'user', textarea: 'document-text', number: 'hashtag', email: 'envelope', url: 'link',
        phone: 'device-mobile', address: 'map-pin', select: 'chevron-down',
        radio: 'check-circle', checkbox: 'check-square', signature: 'pencil',
        file: 'paper-clip', step: 'arrow-right', section: 'minus', title: 'type',
        image: 'photograph', product: 'shopping-bag', captcha: 'shield-check',
        image: 'photograph', product: 'shopping-bag', captcha: 'shield-check',
        consent: 'badge-check', date: 'calendar', time: 'clock', time_range: 'clock',
        smart_field: 'lightning-bolt'
    };
    return icons[type] || 'cube';
}

function getIconSvgByName(name) {
    // Simple mock of SVG paths for common names
    const path = {
        'user': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'document-text': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'hashtag': 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
        'envelope': 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'device-mobile': 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        'map-pin': 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'calendar': 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'clock': 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'shopping-bag': 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        'shield-check': 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-10.618 5.04C5.163 11.411 7.234 16.326 12 21c4.766-4.674 6.837-9.589 10.618-13.016z',
        'pencil': 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
        'paper-clip': 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'photograph': 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'link': 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        'type-text': 'M4 6h16M4 12h16M4 18h7',
        'lightning-bolt': 'M13 10V3L4 14h7v7l9-11h-7z'
    };
    return `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path[name] || 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'}"></path></svg>`;
}

// Advanced Logic Helpers
function toggleLogic() {
    const container = document.getElementById('logic-builder-container');
    const isHidden = container.classList.contains('hidden');
    
    if(isHidden) {
        container.classList.remove('hidden');
        document.getElementById('btn-toggle-logic').innerText = 'Deshabilitar';
        document.getElementById('btn-toggle-logic').classList.replace('text-indigo-600', 'text-red-500');
        // Add one empty rule if none exist
        if(document.getElementById('logic-rules-list').children.length === 0) addLogicRule();
    } else {
        container.classList.add('hidden');
        document.getElementById('btn-toggle-logic').innerText = 'Habilitar';
        document.getElementById('btn-toggle-logic').classList.replace('text-red-500', 'text-indigo-600');
    }
    saveFieldProperties(true);
}

function renderLogicRules(rules, currentIdx) {
    const list = document.getElementById('logic-rules-list');
    list.innerHTML = '';
    
    if(!rules || rules.length === 0) {
        addLogicRule(null, currentIdx);
        return;
    }

    rules.forEach(rule => addLogicRule(rule, currentIdx));
}

function addLogicRule(rule = null, currentIdx = -1) {
    if(currentIdx === -1) currentIdx = currentFieldIdx;
    
    const div = document.createElement('div');
    div.className = 'logic-rule-row flex gap-2 items-center animate-in fade-in slide-in-from-left-2 duration-300';
    
    let dbFieldsOptions = '<option value="">(Campo)</option>';
    formData.fields.slice(0, currentIdx).forEach((prev, pIdx) => {
        if(['text', 'number', 'select', 'radio', 'checkbox', 'email'].includes(prev.type)) {
            // Use stable name if available, fallback to index for legacy
            const fieldVal = prev.name || pIdx;
            const isSel = rule && (rule.field == fieldVal || rule.field == pIdx) ? 'selected' : '';
            dbFieldsOptions += `<option value="${fieldVal}" ${isSel} data-type="${prev.type}" data-idx="${pIdx}">${prev.label || 'Campo #' + (pIdx+1)}</option>`;
        }
    });

    const op = rule ? rule.op : 'equals';
    const val = rule ? rule.value : '';

    div.innerHTML = `
        <select class="rule-field flex-1 bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" onchange="updateRuleValueInput(this); saveFieldProperties(true)">
            ${dbFieldsOptions}
        </select>
        <select class="rule-op w-24 bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" onchange="saveFieldProperties(true)">
            <option value="equals" ${op==='equals'?'selected':''}>Es igual</option>
            <option value="not_equals" ${op==='not_equals'?'selected':''}>No es igual</option>
            <option value="contains" ${op==='contains'?'selected':''}>Contiene</option>
            <option value="greater" ${op==='greater'?'selected':''}>Mayor que</option>
            <option value="less" ${op==='less'?'selected':''}>Menor que</option>
        </select>
        <div class="rule-value-container flex-1">
            <input type="text" class="rule-value w-full bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Valor" value="${val}" oninput="saveFieldProperties(true)">
        </div>
        <button onclick="this.parentElement.remove(); saveFieldProperties(true);" class="text-gray-300 hover:text-red-500 transition-colors p-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;
    
    document.getElementById('logic-rules-list').appendChild(div);
    
    // Initial value input check
    const fieldSelect = div.querySelector('.rule-field');
    if(fieldSelect.value) updateRuleValueInput(fieldSelect, val);

    if(!rule) saveFieldProperties(true);
}

function updateRuleValueInput(select, currentVal = '') {
    const row = select.parentElement;
    const container = row.querySelector('.rule-value-container');
    const selectedOption = select.options[select.selectedIndex];
    const fieldIdx = selectedOption.dataset.idx;
    
    if(!fieldIdx) {
        container.innerHTML = `<input type="text" class="rule-value w-full bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Valor" value="${currentVal}" oninput="saveFieldProperties(true)">`;
        return;
    }

    const field = formData.fields[fieldIdx];
    if(['select', 'radio', 'checkbox'].includes(field.type) && field.options && field.options.length > 0) {
        let optionsHtml = field.options.map(opt => `<option value="${opt}" ${opt === currentVal ? 'selected' : ''}>${opt}</option>`).join('');
        container.innerHTML = `
            <select class="rule-value w-full bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" onchange="saveFieldProperties(true)">
                <option value="">(Selecciona)</option>
                ${optionsHtml}
            </select>
        `;
    } else {
        container.innerHTML = `<input type="text" class="rule-value w-full bg-white border border-gray-100 rounded-lg px-2 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-indigo-500" placeholder="Valor" value="${currentVal}" oninput="saveFieldProperties(true)">`;
    }
}
</script>
