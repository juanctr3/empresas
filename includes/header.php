<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si no hay sesión y no estamos en login.php o api_auth.php
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'api_auth.php') {
    header("Location: login.php");
    exit;
}

// Bloqueo de seguridad: Si requiere configurar contraseña, forzar redirección
if (isset($_SESSION['user_id'])) {
    // Si el usuario requiere configuración y NO está en la página de configuración ni es un logout
    $is_config_page = basename($_SERVER['PHP_SELF']) === 'configurar-password.php';
    
    // Necesitamos verificar el estado actual en la BD por si acaba de cambiar
    require_once 'db.php';
    $stmt_setup = $pdo->prepare("SELECT requires_password_setup, password_reset_token FROM usuarios WHERE id = ?");
    $stmt_setup->execute([$_SESSION['user_id']]);
    $user_setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);

    if ($user_setup && $user_setup['requires_password_setup'] && !empty($user_setup['password_reset_token']) && !$is_config_page && basename($_SERVER['PHP_SELF']) !== 'logout.php') {
        header("Location: configurar-password.php?token=" . $user_setup['password_reset_token'] . "&setup_required=1");
        exit;
    }
}
require_once 'includes/auth_helper.php';

// Cabeceras de Seguridad
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
// CSP básico - Ajustar según necesidades (ej: permitir CDs si se usan)
header("Content-Security-Policy: default-src 'self'; connect-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://user-images.githubusercontent.com;");

// Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CoticeFacil - Sistema de Cotizaciones</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-20 md:pb-0">
    <div class="aurora-bg"></div>
    
    <?php if (isset($_SESSION['simulated_empresa_id'])): ?>
        <div class="bg-indigo-600 text-white px-4 py-2 flex justify-between items-center sticky top-0 z-[100] shadow-xl animate-in slide-in-from-top duration-500">
            <div class="flex items-center gap-3">
                <span class="flex h-3 w-3 relative">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                </span>
                <span class="text-xs font-black uppercase tracking-widest leading-none">Modo Soporte: <span class="text-indigo-200"><?php echo $_SESSION['simulated_empresa_nombre']; ?></span></span>
            </div>
            <button onclick="detenerSoporte()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Detener Soporte</button>
        </div>
        <script>
        async function detenerSoporte() {
            try {
                const res = await fetch('api_saas_imitate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'stop' })
                });
                const data = await res.json();
                if (data.status === 'success') window.location.href = 'saas_dashboard.php';
            } catch(e) { console.error(e); }
        }
        </script>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Sidebar Desktop -->
    <aside class="sidebar hidden md:flex flex-col glass border-r">
        <!-- Toggle Button (Absolute) -->


        <div class="p-8 flex items-center justify-between overflow-hidden">
            <a href="index.php" class="flex items-center gap-3 min-w-max">
                <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200 shrink-0">
                    <span class="text-white font-bold text-2xl">C</span>
                </div>
                <div class="flex flex-col sidebar-logo-text transition-opacity duration-200">
                    <span class="text-2xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-pink-500 whitespace-nowrap">CoticeFacil</span>
                    <?php 
                    $nombre_empresa_actual = $_SESSION['simulated_empresa_nombre'] ?? $_SESSION['empresa_nombre'] ?? 'Sistema';
                    ?>
                    <span class="text-[8px] font-black uppercase tracking-widest text-gray-400 opacity-60 whitespace-nowrap"><?php echo htmlspecialchars($nombre_empresa_actual); ?></span>
                </div>
            </a>
        </div>
        
        <!-- Search Bar -->
        <!-- Search Bar -->
        <div class="px-7 mb-6 relative sidebar-search transition-opacity duration-200">
            <div class="relative group">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" id="globalSearchInput" placeholder="Buscar..." class="w-full bg-gray-50/50 text-xs font-bold text-gray-700 rounded-xl pl-9 pr-3 py-3 border border-gray-100 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder:text-gray-400" autocomplete="off">
                <!-- Loader -->
                <span id="globalSearchLoader" class="absolute right-3 top-1/2 -translate-y-1/2 hidden text-indigo-500">
                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </span>
            </div>
            
            <!-- Search Results Dropdown -->
            <div id="globalSearchResults" class="hidden absolute top-full left-0 right-0 mx-6 mt-2 bg-white rounded-2xl shadow-2xl border border-gray-50 z-50 overflow-hidden max-h-80 overflow-y-auto animate-in fade-in zoom-in-95 duration-200">
                <!-- Js Injected Results -->
            </div>
        </div>

        <script>
        const searchInput = document.getElementById('globalSearchInput');
        const searchResults = document.getElementById('globalSearchResults');
        const searchLoader = document.getElementById('globalSearchLoader');
        let searchTimeout;

        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.trim();
            if(searchTimeout) clearTimeout(searchTimeout);

            if(q.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchLoader.classList.remove('hidden');
            searchTimeout = setTimeout(() => {
                fetch(`api_global_search.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if(data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('a');
                            div.href = item.url;
                            div.className = 'flex items-center gap-3 p-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0 group';
                            div.innerHTML = `
                                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                    ${item.icon}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-800 truncate group-hover:text-indigo-700">${item.label}</div>
                                    <div class="text-[10px] text-gray-400 capitalize truncate">${item.type} • ${item.sub}</div>
                                </div>
                            `;
                            searchResults.appendChild(div);
                        });
                        searchResults.classList.remove('hidden');
                    } else {
                        searchResults.innerHTML = '<div class="p-4 text-center text-xs text-gray-400 italic">No se encontraron resultados</div>';
                        searchResults.classList.remove('hidden');
                    }
                })
                .finally(() => searchLoader.classList.add('hidden'));
            }, 300);
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if(!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
        </script>
        
        <nav class="flex-1 overflow-y-auto px-2 space-y-1 custom-scrollbar">
            <a href="index.php" class="sidebar-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="sidebar-text">Inicio</span>
            </a>

            <?php if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true): ?>
                <a href="saas_dashboard.php" class="sidebar-item <?php echo $current_page == 'saas_dashboard.php' ? 'active' : ''; ?> border-l-4 border-indigo-600 bg-indigo-50/50">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span class="font-black text-indigo-700 sidebar-text">SaaS Panel</span>
                </a>
            <?php endif; ?>
            
            <?php if(tienePermiso('ver_cotizaciones')): ?>
                <a href="prospectos.php" class="sidebar-item <?php echo $current_page == 'prospectos.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span class="sidebar-text">Prospectos</span>
                </a>
                <?php if(tienePermiso('acceso_whatsapp')): ?>
                    <a href="whatsapp.php" class="sidebar-item <?php echo $current_page == 'whatsapp.php' ? 'active' : ''; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        <span class="sidebar-text">WhatsApp CRM</span>
                    </a>
                <?php endif; ?>
                <a href="clientes.php" class="sidebar-item <?php echo $current_page == 'clientes.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span class="sidebar-text">Clientes</span>
                </a>
                <a href="cotizaciones.php" class="sidebar-item <?php echo $current_page == 'cotizaciones.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span class="sidebar-text">Cotizaciones</span>
                </a>
                
                <a href="recordatorios.php" class="sidebar-item <?php echo $current_page == 'recordatorios.php' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span class="sidebar-text">Recordatorios</span>
                </a>

                <?php if(tienePermiso('ver_facturas') || tienePermiso('ver_cotizaciones')): // Fallback temp ?>
                <a href="facturas.php" class="sidebar-item <?php echo $current_page == 'facturas.php' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="sidebar-text">Facturación</span>
                </a>
                <?php endif; ?>
                <a href="ordenes.php" class="sidebar-item <?php echo $current_page == 'ordenes.php' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span class="sidebar-text">Órdenes</span>
                </a>
                <a href="alertas.php" class="sidebar-item <?php echo $current_page == 'alertas.php' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="sidebar-text">Alertas</span>
                </a>
                <a href="certificados.php" class="sidebar-item <?php echo $current_page == 'certificados.php' ? 'active' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    <span class="sidebar-text">Certificados</span>
                </a>
                <!-- Documentos -->
            <a href="documentos.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl transition-all duration-300 <?php echo $current_page == 'documentos.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-600 hover:bg-indigo-50 hover:text-indigo-600'; ?> group">
                <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <span class="font-bold text-sm sidebar-text">Bóveda Doc</span>
            </a>

            <!-- Clientes Confianza -->
            <a href="clientes_confianza.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl transition-all duration-300 <?php echo $current_page == 'clientes_confianza.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-600 hover:bg-indigo-50 hover:text-indigo-600'; ?> group">
                <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z"></path></svg>
                <span class="font-bold text-sm sidebar-text">Logos Social</span>
            </a>
                <a href="productos.php" class="sidebar-item <?php echo $current_page == 'productos.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span class="sidebar-text">Productos</span>
                </a>
                <a href="plantillas.php" class="sidebar-item <?php echo $current_page == 'plantillas.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                    <span class="sidebar-text">Plantillas</span>
                </a>
                
                <a href="formularios.php" class="sidebar-item <?php echo $current_page == 'formularios.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    <span class="sidebar-text">Formularios</span>
                </a>
            <?php endif; ?>

            <?php if(tienePermiso('gestionar_empleados')): ?>
                <a href="empleados.php" class="sidebar-item <?php echo $current_page == 'empleados.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span class="sidebar-text">Empleados</span>
                </a>
            <?php endif; ?>

            <?php if(tienePermiso('configuracion_general')): ?>
                <a href="configuracion.php" class="sidebar-item <?php echo $current_page == 'configuracion.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span class="sidebar-text">Configuración</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="p-4 mt-auto space-y-2">
            <!-- Campana de Notificaciones -->
            <div class="relative">
                <button id="notifications-bell" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-gray-100 transition-all group relative">
                    <div class="relative w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-gray-200 transition-colors">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span id="notifications-badge" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center shadow-lg">0</span>
                    </div>
                    <span class="text-sm font-bold text-gray-700 sidebar-text">Notificaciones</span>
                </button>

                <!-- Dropdown de Notificaciones -->
                <div id="notifications-dropdown" class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden z-50 max-h-[500px] flex flex-col">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                        <h3 class="text-sm font-black text-gray-900">Notificaciones</h3>
                        <button onclick="marcarTodasLeidas()" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 uppercase tracking-wider">
                            Marcar todas
                        </button>
                    </div>
                    <div id="notifications-list" class="flex-1 overflow-y-auto">
                        <!-- Se llena dinámicamente -->
                    </div>
                    <div class="p-3 border-t border-gray-100 text-center">
                        <a href="notificaciones.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">Ver todas las notificaciones →</a>
                    </div>
                </div>
            </div>

            <!-- Perfil -->
            <a href="perfil.php" class="flex items-center gap-3 p-3 rounded-2xl bg-gray-50/50 hover:bg-gray-100 transition-all group">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold group-hover:bg-indigo-200 transition-colors">
                    <?php echo htmlspecialchars(substr($_SESSION['user_nombre'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0 text-left">
                    <p class="text-sm font-bold text-gray-900 truncate sidebar-text"><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></p>
                    <p class="text-[10px] uppercase font-black tracking-widest text-indigo-500 sidebar-text"><?php echo htmlspecialchars($_SESSION['user_rol']); ?></p>
                </div>
                <div href="logout.php" onclick="event.preventDefault(); window.location.href='logout.php';" class="p-2 text-gray-400 hover:text-red-500 transition-colors relative z-10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </div>
            </a>
        </div>
    </aside>



    <!-- Mobile Top Header -->
    <header class="md:hidden glass sticky top-0 z-40 px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-pink-500">CoticeFacil</span>
            <?php 
            $nombre_empresa_actual = $_SESSION['simulated_empresa_nombre'] ?? $_SESSION['empresa_nombre'] ?? 'Sistema';
            ?>
            <span class="px-2 py-0.5 bg-gray-900 text-white text-[8px] font-black uppercase rounded-lg tracking-tighter opacity-80"><?php echo htmlspecialchars($nombre_empresa_actual); ?></span>
        </div>
        <div class="flex items-center gap-3">
            <!-- Campana Mobile -->
            <button id="notifications-bell-mobile" onclick="toggleNotificationsDropdown()" class="relative w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span id="notifications-badge-mobile" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center shadow-lg">0</span>
            </button>
            <a href="perfil.php" class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm hover:bg-indigo-200 transition-colors">
                <?php echo substr($_SESSION['user_nombre'], 0, 1); ?>
            </a>
        </div>
    </header>

    <!-- Dropdown Mobile de Notificaciones (Fullscreen) -->
    <div id="notifications-dropdown-mobile" class="hidden fixed inset-0 z-50 bg-white md:hidden">
        <div class="flex flex-col h-full">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                <h3 class="text-lg font-black text-gray-900">Notificaciones</h3>
                <button onclick="document.getElementById('notifications-dropdown-mobile').classList.add('hidden')" class="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center transition-colors">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto" id="notifications-list-mobile">
                <!-- Se llena dinámicamente -->
            </div>
            <div class="p-4 border-t border-gray-100 flex gap-2">
                <button onclick="marcarTodasLeidas()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-colors">
                    Marcar todas leídas
                </button>
                <a href="notificaciones.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition-colors text-center">
                    Ver todas
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Floating Dock Nav -->
    <nav class="md:hidden fixed bottom-6 left-4 right-4 h-16 bg-white/90 backdrop-blur-xl border border-white/40 rounded-[2rem] shadow-2xl flex items-center justify-between px-6 z-50">
        <!-- Inicio -->
        <a href="index.php" class="flex flex-col items-center justify-center w-10 text-gray-400 hover:text-indigo-600 transition-colors <?php echo $current_page == 'index.php' ? 'text-indigo-600' : ''; ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        </a>
        
        <!-- Clientes -->
        <a href="clientes.php" class="flex flex-col items-center justify-center w-10 text-gray-400 hover:text-indigo-600 transition-colors <?php echo $current_page == 'clientes.php' ? 'text-indigo-600' : ''; ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </a>

        <!-- Add Button (Floating) -->
        <a href="nueva-cotizacion.php" class="relative -top-6">
            <div class="w-14 h-14 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-indigo-500/40 border-4 border-[#f8fafc] transform hover:scale-105 transition-transform">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <!-- Ripple Effect -->
            <div class="absolute inset-0 rounded-full bg-indigo-600 animate-ping opacity-20 -z-10"></div>
        </a>

        <!-- Cotizaciones -->
        <a href="cotizaciones.php" class="flex flex-col items-center justify-center w-10 text-gray-400 hover:text-indigo-600 transition-colors <?php echo $current_page == 'cotizaciones.php' ? 'text-indigo-600' : ''; ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </a>

        <!-- Menu Drawer Trigger -->
        <button onclick="toggleMobileDrawer()" class="flex flex-col items-center justify-center w-10 text-gray-400 hover:text-indigo-600 transition-colors">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
        </button>
    </nav>

    <!-- Mobile Drawer (More Menu) -->
    <!-- Backdrop separate from drawer to avoid transform issues -->
    <div id="mobileDrawerBackdrop" class="md:hidden fixed inset-0 bg-gray-900/20 backdrop-blur-sm z-[55] hidden transition-opacity duration-300" onclick="toggleMobileDrawer()"></div>

    <div id="mobileDrawer" class="md:hidden fixed inset-x-0 bottom-0 z-[60] transform translate-y-full transition-transform duration-300 ease-in-out">
        
        <!-- Drawer Content -->
        <div class="bg-white/90 backdrop-blur-xl rounded-t-[2.5rem] shadow-2xl p-6 pb-28 border-t border-white/50 space-y-6">
            <div class="w-12 h-1.5 bg-gray-300 rounded-full mx-auto mb-2 opacity-50"></div>
            
            <div class="grid grid-cols-4 gap-4">
                <a href="prospectos.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Prospectos</span>
                </a>
                
                <?php if(tienePermiso('acceso_whatsapp')): ?>
                <a href="whatsapp.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center group-hover:bg-green-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">CRM WhatsApp</span>
                </a>
                <?php endif; ?>

                <?php if(tienePermiso('ver_facturas')): ?>
                <a href="facturas.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Facturas</span>
                </a>
                <?php endif; ?>

                <a href="productos.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center group-hover:bg-orange-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Productos</span>
                </a>
                
                <a href="recordatorios.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Alertas</span>
                </a>
                
                <a href="plantillas.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center group-hover:bg-pink-600 group-hover:text-white transition-colors">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Plantillas</span>
                </a>

                <a href="formularios.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Formularios</span>
                </a>

                <a href="certificados.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Certificados</span>
                </a>

                <a href="documentos.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center group-hover:bg-violet-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Bóveda Doc</span>
                </a>

                <a href="clientes_confianza.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-fuchsia-50 text-fuchsia-600 flex items-center justify-center group-hover:bg-fuchsia-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Logos Social</span>
                </a>

                <a href="configuracion.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 text-gray-600 flex items-center justify-center group-hover:bg-gray-800 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Ajustes</span>
                </a>
                
                <a href="perfil.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Mi Perfil</span>
                </a>
                
                <a href="logout.php" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </div>
                    <span class="text-[10px] font-bold text-gray-600">Salir</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <main class="main-content flex-grow">


<script>
    function toggleMobileDrawer() {
        const drawer = document.getElementById('mobileDrawer');
        const backdrop = document.getElementById('mobileDrawerBackdrop');
        if (drawer.classList.contains('translate-y-full')) {
            drawer.classList.remove('translate-y-full');
            backdrop.classList.remove('hidden');
            backdrop.classList.remove('opacity-0');
        } else {
            drawer.classList.add('translate-y-full');
            backdrop.classList.add('hidden');
            backdrop.classList.add('opacity-0');
        }
    }
</script>

