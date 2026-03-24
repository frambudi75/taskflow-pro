<?php
// pages/chat.php
if (!isLoggedIn()) exit;

$currentUser = $_SESSION['user_id'];
$targetUser  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// 1. Fetch CURRENT ACTIVE conversations (people you've chatted with)
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.display_name, u.role, u.profile_photo 
    FROM users u
    JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$currentUser, $currentUser, $currentUser]);
$activeConversations = $stmt->fetchAll();

// 2. Fetch ALL users for the "New Chat" modal search
$stmt = $pdo->prepare("SELECT id, display_name, role FROM users WHERE id != ? ORDER BY display_name ASC");
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
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white mb-1">Pesan</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kontak Aktif</p>
            </div>
            <button onclick="openNewChatModal()" class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-600/20 active:scale-95" title="Mulai Chat Baru">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
            <?php if (empty($activeConversations)): ?>
                <div class="text-center py-10 px-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-relaxed">Belum ada obrolan aktif. Klik (+) untuk memulai.</p>
                </div>
            <?php endif; ?>
            
            <?php foreach ($activeConversations as $u): ?>
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
                <div class="flex items-center gap-3">
                    <button onclick="clearChat()" class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all active:scale-90" title="Bersihkan Riwayat Chat">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                    <a href="index.php?page=chat" class="md:hidden w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </a>
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

<!-- New Chat Modal -->
<div id="newChatModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="closeNewChatModal()"></div>
    <div class="relative bg-white dark:bg-slate-900 w-full max-w-md rounded-[2.5rem] shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden scale-100 transition-all duration-300">
        <div class="p-8 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Mulai Chat Baru</h3>
            <div class="relative mt-4">
                <input type="text" id="userSearchInput" onkeyup="filterUsers()" 
                       placeholder="Cari nama kolega..." 
                       class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all pl-12">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
            </div>
        </div>
        <div class="max-h-[350px] overflow-y-auto p-4 space-y-2 custom-scrollbar" id="userSearchList">
            <?php foreach ($allUsers as $u): ?>
                <a href="index.php?page=chat&user_id=<?php echo $u['id']; ?>" 
                   class="user-row flex items-center gap-4 p-4 rounded-3xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 flex items-center justify-center font-black group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <?php echo strtoupper(substr($u['display_name'], 0, 1)); ?>
                    </div>
                    <div class="flex-1">
                        <div class="text-[13px] font-black text-slate-900 dark:text-white search-name"><?php echo htmlspecialchars($u['display_name']); ?></div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $u['role']; ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="p-6 bg-slate-50 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800 text-center">
            <button onclick="closeNewChatModal()" class="text-[11px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-600 dark:hover:text-slate-200 transition-all">TUTUP</button>
        </div>
    </div>
</div>

<script>
const targetId = <?php echo $targetUser; ?>;
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const messageInput = document.getElementById('messageInput');

function openNewChatModal() {
    document.getElementById('newChatModal').classList.remove('hidden');
    document.getElementById('userSearchInput').focus();
}

function closeNewChatModal() {
    document.getElementById('newChatModal').classList.add('hidden');
}

function filterUsers() {
    const q = document.getElementById('userSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    rows.forEach(row => {
        const name = row.querySelector('.search-name').innerText.toLowerCase();
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

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
                        <div class="flex ${isMe ? 'justify-end' : 'justify-start'} group">
                            <div class="max-w-[75%] md:max-w-[60%] relative">
                                ${isMe ? `<button onclick="deleteMessage(${msg.id})" class="absolute -left-8 top-2 p-1 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : ''}
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

function deleteMessage(id) {
    if (!confirm('Hapus pesan ini?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_msg');
    fd.append('message_id', id);
    fetch('api_chat.php', { method: 'POST', body: fd }).then(() => fetchMessages());
}

function clearChat() {
    if (!confirm('Bersihkan semua riwayat chat dengan user ini? Tindakan ini tidak bisa dibatalkan.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_all');
    fd.append('user_id', targetId);
    fetch('api_chat.php', { method: 'POST', body: fd }).then(() => {
        location.href = 'index.php?page=chat';
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
