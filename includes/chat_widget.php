<div id="wdg-chat-container" class="fixed bottom-24 right-6 z-[9999] flex flex-col items-end gap-2 font-san transition-all duration-300">
    <!-- Chat Window (Collapsible) -->
    <div id="wdg-chat-window" class="hidden w-[95%] md:w-[400px] h-[70vh] bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 flex-col animate-in slide-in-from-bottom-10 fade-in duration-300 origin-bottom-right">
        <!-- Header -->
        <div class="bg-[#00a884] p-4 flex justify-between items-center text-white cursor-move" id="wdg-header">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                <div class="flex flex-col">
                    <span class="font-bold text-sm leading-tight">Chat de Ventas</span>
                    <span class="text-[10px] text-green-100 opacity-90" id="wdg-status">En línea</span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                 <button onclick="toggleChatInfo()" class="p-1.5 hover:bg-white/10 rounded-full transition-colors" title="Información">
                    <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                <button onclick="toggleChatWidget()" class="p-1.5 hover:bg-white/10 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>
        </div>

        <div class="flex-1 bg-[#efeae2] relative">
            <!-- Loader REMOVED for debugging -->
            <iframe id="wdg-iframe" src="whatsapp.php?embedded=1&t=<?php echo time(); ?>" class="w-full h-full border-none" loading="lazy"></iframe>
        </div>
    </div>

    <!-- Floating Trigger Button -->
    <button id="wdg-trigger" onclick="toggleChatWidget()" class="group relative flex items-center justify-center w-14 h-14 bg-[#25d366] hover:bg-[#20bd5a] text-white rounded-full shadow-lg hover:shadow-green-500/30 transition-all duration-300 hover:scale-105 active:scale-95">
        <svg class="w-8 h-8 pointer-events-none" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        
        <!-- Notification Badge -->
        <span id="wdg-badge" class="absolute -top-1 -right-1 flex h-5 w-5 ml-auto hidden">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-[10px] items-center justify-center font-bold" id="wdg-badge-count">0</span>
        </span>
    </button>
</div>

<script>
    // State
    let isChatOpen = false;
    let isDragging = false;
    let initialX, initialY, currentX, currentY, xOffset = 0, yOffset = 0;
    const chatContainer = document.getElementById("wdg-chat-container");
    const chatWindow = document.getElementById("wdg-chat-window");
    const iframe = document.getElementById("wdg-iframe");
    const loader = document.getElementById("wdg-loader");
    const header = document.getElementById("wdg-header");
    const badge = document.getElementById("wdg-badge");
    const badgeCount = document.getElementById("wdg-badge-count");

    // Load State from LocalStorage
    // Removed persistency of 'open' state to avoid annoyance on navigation
    // if (localStorage.getItem('chatWidgetOpen') === 'true') toggleChatWidget();

    // Toggle Chat
    function toggleChatWidget(forceState = null) {
        if (forceState !== null) isChatOpen = !forceState;
        
        isChatOpen = !isChatOpen;
        
        if (isChatOpen) {
            chatWindow.classList.remove("hidden");
            // Reload if empty (safety)
            if (!iframe.src || iframe.src === "" || iframe.src.includes("about:blank")) {
                 iframe.src = "debug_embedded.php?t=" + new Date().getTime();
                 iframe.onload = () => {
                    loader.classList.add("opacity-0");
                    setTimeout(() => loader.classList.add("hidden"), 300);
                 };
                 // Safety timeout
                 setTimeout(() => {
                     console.log("Forcing loader hide");
                     loader.classList.add("opacity-0");
                     setTimeout(() => loader.classList.add("hidden"), 300);
                 }, 3000);
            }
        } else {
            chatWindow.classList.add("hidden");
        }
        
        localStorage.setItem('chatWidgetOpen', isChatOpen);
    }

    // Polling for Notifications
    async function checkUnreadMessages() {
        try {
            const res = await fetch('api_check_unread.php'); 
            const data = await res.json();
            
            if (data.unread_count > 0) {
                badge.classList.remove('hidden');
                badgeCount.innerText = data.unread_count > 99 ? '99+' : data.unread_count;
                // Play sound if count increased? (future enhancement)
            } else {
                badge.classList.add('hidden');
            }
        } catch (e) {
            // console.error(e);
        }
    }
    
    // Poll every 30s
    checkUnreadMessages();
    setInterval(checkUnreadMessages, 30000);

    // Drag Logic (Basic implementation for desktop)
    header.addEventListener("mousedown", dragStart);
    document.addEventListener("mouseup", dragEnd);
    document.addEventListener("mousemove", drag);

    function dragStart(e) {
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
        if (e.target === header || header.contains(e.target)) {
            isDragging = true;
        }
    }

    function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    }

    function drag(e) {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            xOffset = currentX;
            yOffset = currentY;

            setTranslate(currentX, currentY, chatContainer);
        }
    }

    function setTranslate(xPos, yPos, el) {
        el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
    }
</script>
