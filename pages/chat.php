<?php
// pages/chat.php
if (!isLoggedIn()) exit;

$currentUser = $_SESSION['user_id'];
$targetUser  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch all users except self
$stmt = $pdo->prepare("SELECT id, display_name, role, profile_photo FROM users WHERE id != ? ORDER BY display_name ASC");
$stmt->execute([$currentUser]);
$allUsers = $stmt->fetchAll();

// If no target user, pick the first one from list if available
if ($targetUser === 0 && !empty($allUsers)) {
    $targetUser = $allUsers[0]['id'];
}

// Fetch target user info
$targetInfo = null;
if ($targetUser > 0) {
    $stmt = $pdo->prepare("SELECT display_name, role FROM users WHERE id = ?");
    $stmt->execute([$targetUser]);
    $targetInfo = $stmt->fetch();
}
?>

<div class="h-[calc(100vh-140px)] flex flex-col md:flex-row bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-[2rem] overflow-hidden shadow-sm m-4 md:m-6">
    
    <!-- User List (Sidebar) -->
    <div class="w-full md:w-80 flex-shrink-0 border-r border-slate-100 dark:border-slate-800 flex flex-col bg-slate-50/50 dark:bg-slate-800/10">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-lg font-black text-slate-900 dark:text-white mb-1">Pesan</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kontak Internal</p>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
            <?php foreach ($allUsers as $u): ?>
                <a href="index.php?page=chat&user_id=<?php echo $u['id']; ?>" 
                   class="flex items-center gap-3 p-3 rounded-2xl transition-all <?php echo $targetUser === $u['id'] ? 'bg-indigo-600 shadow-lg shadow-indigo-600/20 text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'; ?>">
                    <div class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden font-bold">
                        <?php if($u['profile_photo']): ?>
                            <img src="uploads/avatars/<?php echo $u['profile_photo']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($u['display_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold truncate"><?php echo htmlspecialchars($u['display_name']); ?></div>
                        <div class="text-[10px] opacity-60 font-medium uppercase tracking-tighter"><?php echo $u['role']; ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col min-w-0 bg-white dark:bg-slate-900 relative">
        <?php if ($targetInfo): ?>
            <!-- Header -->
            <div class="p-4 md:p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between z-10 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold">
                        <?php echo strtoupper(substr($targetInfo['display_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($targetInfo['display_name']); ?></h4>
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Online</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar bg-slate-50/30 dark:bg-slate-950/20">
                <!-- Loaded via AJAX -->
                <div class="flex justify-center py-10 opacity-50">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 md:p-6 border-t border-slate-100 dark:border-slate-800">
                <form id="chatForm" class="flex items-center gap-3">
                    <input type="hidden" name="receiver_id" value="<?php echo $targetUser; ?>">
                    <input type="text" id="messageInput" name="message" required autocomplete="off"
                           placeholder="Ketik pesan di sini..." 
                           class="flex-1 bg-slate-100 dark:bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all">
                    <button type="submit" class="w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-600/20 transition-all active:scale-95">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polyline points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-1 flex flex-col items-center justify-center p-10 text-center opacity-40">
                <div class="w-20 h-20 rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-6">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-10.6 8.38 8.38 0 0 1 3.8.9L22 4l-2 5z"/></svg>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Pilih Kontak</h3>
                <p class="text-sm font-medium text-slate-500 max-w-xs">Pilih salah satu teman kerja kamu di sisi kiri untuk mulai mengobrol.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const targetId = <?php echo $targetUser; ?>;
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const messageInput = document.getElementById('messageInput');

function fetchMessages() {
    if (targetId === 0) return;
    
    fetch('api_chat.php?action=get&user_id=' + targetId)
        .then(response => response.json())
        .then(data => {
            const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
            
            let html = '';
            if (data.length === 0) {
                html = '<div class="text-center py-20 opacity-30 text-xs font-bold uppercase tracking-widest">Belum ada obrolan</div>';
            } else {
                data.forEach(msg => {
                    const isMe = msg.sender_id == <?php echo $currentUser; ?>;
                    html += `
                        <div class="flex ${isMe ? 'justify-end' : 'justify-start'}">
                            <div class="max-w-[75%] md:max-w-[60%]">
                                <div class="p-4 rounded-3xl text-sm font-medium shadow-sm ${isMe ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-100 dark:border-slate-800 rounded-tl-none'}">
                                    ${msg.message}
                                </div>
                                <div class="mt-1.5 px-1 text-[9px] font-bold text-slate-400 uppercase tracking-tighter ${isMe ? 'text-right' : 'text-left'}">
                                    ${msg.time} ${isMe ? (msg.is_read == 1 ? '· DIBACA' : '· TERKIRIM') : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            chatMessages.innerHTML = html;
            
            if (wasAtBottom) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
}

if (chatForm) {
    chatForm.onsubmit = function(e) {
        e.preventDefault();
        const msg = messageInput.value.trim();
        if (!msg) return;
        
        const formData = new FormData(chatForm);
        formData.append('action', 'send');
        
        messageInput.value = '';
        
        fetch('api_chat.php', {
            method: 'POST',
            body: formData
        }).then(() => {
            fetchMessages();
        });
    };
}

// Initial fetch
if (targetId > 0) {
    fetchMessages();
    // Poll every 3 seconds
    setInterval(fetchMessages, 3000);
}
</script>
