<?php
// pages/chat.php
if (!isLoggedIn()) exit;

$currentUser = $_SESSION['user_id'];
$targetUser  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// 1. Fetch CURRENT ACTIVE conversations (people you've chatted with)
$stmt = $pdo->prepare("
    SELECT u.id, u.display_name, u.role, u.profile_photo,
           (SELECT message FROM messages 
            WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT created_at FROM messages 
            WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY created_at DESC LIMIT 1) as last_time,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_from_user
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
        FROM messages WHERE sender_id = ? OR receiver_id = ?
    )
    ORDER BY last_time DESC
");
$stmt->execute([$currentUser, $currentUser, $currentUser, $currentUser, $currentUser, $currentUser, $currentUser, $currentUser]);
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
    $stmt = $pdo->prepare("SELECT display_name, role, profile_photo FROM users WHERE id = ?");
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
                    <div class="w-12 h-12 rounded-xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden font-bold">
                        <?php if($u['profile_photo']): ?>
                            <img src="uploads/avatars/<?php echo $u['profile_photo']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($u['display_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <div class="text-[13px] font-black truncate"><?php echo htmlspecialchars($u['display_name']); ?></div>
                            <div class="text-[9px] font-bold opacity-60"><?php echo date('H:i', strtotime($u['last_time'])); ?></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] opacity-60 truncate font-medium max-w-[120px]">
                                <?php echo htmlspecialchars($u['last_msg']); ?>
                            </div>
                            <?php if ($u['unread_from_user'] > 0): ?>
                                <span class="w-5 h-5 bg-emerald-500 text-white rounded-full flex items-center justify-center text-[9px] font-black shadow-lg shadow-emerald-500/20">
                                    <?php echo $u['unread_from_user']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
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
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0 overflow-hidden font-bold">
                        <?php if($targetInfo['profile_photo']): ?>
                            <img src="uploads/avatars/<?php echo $targetInfo['profile_photo']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="text-orange-600"><?php echo strtoupper(substr($targetInfo['display_name'], 0, 1)); ?></div>
                        <?php endif; ?>
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

            <!-- Messages Area with Wallpaper -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar relative bg-[#f0f2f5] dark:bg-[#0b141a]"
                 style="background-image: url('data:image/svg+xml,%3Csvg width=\"100\" height=\"100\" viewBox=\"0 0 100 100\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cpath d=\"M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 35c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm60-17c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM66 46c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM25 76c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM7 39c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM90 71c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM21 34c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM45 44c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM80 80c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 20c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm-6 48c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm35-19c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM24 5c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm57 4c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm-15 1c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm-2 90c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm35-24c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM5 69c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm25 1c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm37 7c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm13 17c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1z\" fill=\"%236366f1\" fill-opacity=\"0.05\" fill-rule=\"evenodd\"/%3E%3C/svg%3E'); font-family: 'Inter', sans-serif;">
                <!-- Loaded via AJAX -->
                <div class="flex justify-center py-10 opacity-50">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 md:p-6 border-t border-slate-100 dark:border-slate-800">
                <form id="chatForm" class="flex items-center gap-3" enctype="multipart/form-data">
                    <input type="hidden" name="receiver_id" value="<?php echo $targetUser; ?>">
                    
                    <button type="button" onclick="document.getElementById('fileAttachInput').click()" 
                            class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-indigo-600 flex items-center justify-center transition-all active:scale-95">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>
                    <input type="file" id="fileAttachInput" name="attachment" class="hidden" onchange="handleFileSelect()">

                    <div class="flex-1 relative">
                        <input type="text" id="messageInput" name="message" autocomplete="off"
                               placeholder="Ketik pesan..." 
                               class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm font-medium text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all">
                        <div id="fileIndicator" class="hidden absolute right-3 top-1/2 -translate-y-1/2 bg-emerald-500 text-white px-2 py-0.5 rounded-lg text-[8px] font-black uppercase flex items-center gap-1">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> FILE SIAP
                        </div>
                    </div>

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
                <div onclick="handleSelection(<?php echo $u['id']; ?>)" 
                   class="user-row flex items-center gap-4 p-4 rounded-3xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all group cursor-pointer">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center flex-shrink-0 overflow-hidden font-black group-hover:bg-indigo-600 transition-all">
                        <?php if($u['profile_photo']): ?>
                            <img src="uploads/avatars/<?php echo $u['profile_photo']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="text-indigo-600 group-hover:text-white"><?php echo strtoupper(substr($u['display_name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="text-[13px] font-black text-slate-900 dark:text-white search-name"><?php echo htmlspecialchars($u['display_name']); ?></div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $u['role']; ?></div>
                    </div>
                </div>
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

let forwardData = null;

function forwardMessage(id, messageBase64, filePath) {
    forwardData = {
        message: atob(messageBase64),
        file_path: filePath
    };
    document.querySelector('#newChatModal h3').innerText = 'Teruskan Pesan Ke...';
    openNewChatModal();
}

function handleSelection(targetId) {
    if (forwardData) {
        // DO FORWARD
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('receiver_id', targetId);
        fd.append('message', forwardData.message);
        if (forwardData.file_path) {
            // Special handling for forwarded files: we just send the file_path
            // But api_chat.php expects 'attachment' OR existing 'message'.
            // Let's modify api_chat.php briefly OR just send it as message if simple.
            // Better: Add a 'forwarded_file' param to api_chat.php
            fd.append('forwarded_file', forwardData.file_path);
        }

        fetch('api_chat.php', { method: 'POST', body: fd }).then(() => {
            alert('Pesan berhasil diteruskan!');
            closeNewChatModal();
            location.href = 'index.php?page=chat&user_id=' + targetId;
        });
    } else {
        location.href = 'index.php?page=chat&user_id=' + targetId;
    }
}

function closeNewChatModal() {
    document.getElementById('newChatModal').classList.add('hidden');
    document.querySelector('#newChatModal h3').innerText = 'Mulai Chat Baru';
    forwardData = null;
}

function filterUsers() {
    const q = document.getElementById('userSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    rows.forEach(row => {
        const name = row.querySelector('.search-name').innerText.toLowerCase();
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

function linkify(text) {
    if (!text) return "";
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, function(url) {
        return `<a href="${url}" target="_blank" class="underline decoration-2 underline-offset-2 hover:text-white/80 transition-all font-black text-indigo-400 dark:text-sky-400">${url}</a>`;
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
                    let attachmentHtml = '';
                    if (msg.file_path) {
                        const ext = msg.file_path.split('.').pop().toLowerCase();
                        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                            attachmentHtml = `<img src="uploads/chat/${msg.file_path}" class="rounded-xl max-w-full h-auto mb-2 cursor-pointer" onclick="window.open(this.src)">`;
                        } else {
                            attachmentHtml = `
                                <a href="uploads/chat/${msg.file_path}" target="_blank" class="flex items-center gap-3 p-3 rounded-xl bg-black/5 dark:bg-white/5 mb-2 hover:bg-black/10 transition-all">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-500 text-white flex items-center justify-center">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                                    </div>
                                    <div class="text-[10px] font-bold truncate max-w-[120px] uppercase">${msg.file_path.split('_').slice(1).join('_')}</div>
                                </a>
                            `;
                        }
                    }

                    html += `
                        <div class="flex ${isMe ? 'justify-end' : 'justify-start'} group">
                            <div class="max-w-[75%] md:max-w-[60%] relative flex items-start gap-2 ${isMe ? 'flex-row-reverse' : 'flex-row'}">
                                <div class="p-4 rounded-3xl text-sm font-medium shadow-sm ${isMe ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-100 dark:border-slate-800 rounded-tl-none'}">
                                    ${attachmentHtml}
                                    ${msg.message ? `<div class="${attachmentHtml ? 'mt-2' : ''}">${linkify(msg.message)}</div>` : ''}
                                </div>
                                
                                <!-- Action Buttons (Hover) -->
                                <div class="flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    ${isMe ? `<button onclick="deleteMessage(${msg.id})" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-300 hover:text-red-500 transition-all" title="Hapus"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : ''}
                                    <button onclick="forwardMessage('${msg.id}', '${btoa(msg.message || '')}', '${msg.file_path || ''}')" class="p-1.5 rounded-lg hover:bg-sky-50 text-slate-300 hover:text-sky-500 transition-all" title="Teruskan">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 3 21 3 21 9"/><path d="M18 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </button>
                                </div>
                                
                                <div class="absolute -bottom-5 ${isMe ? 'right-1' : 'left-5'} flex items-center gap-1 text-[9px] font-bold text-slate-400 uppercase tracking-tighter">
                                    ${msg.time} 
                                    ${isMe ? `
                                        <span class="${msg.is_read == 1 ? 'text-sky-400' : 'text-slate-300'}">
                                            ${msg.is_read == 1 ? 
                                                `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/><polyline points="15 6 9 12 11 15"/></svg>` : 
                                                `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>`}
                                        </span>` : ''}
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

function handleFileSelect() {
    const input = document.getElementById('fileAttachInput');
    const indicator = document.getElementById('fileIndicator');
    if (input.files.length > 0) {
        indicator.classList.remove('hidden');
    } else {
        indicator.classList.add('hidden');
    }
}

if (chatForm) {
    chatForm.onsubmit = function(e) {
        e.preventDefault();
        const msg = messageInput.value.trim();
        const fileInput = document.getElementById('fileAttachInput');
        
        if (!msg && fileInput.files.length === 0) return;
        
        const formData = new FormData(chatForm);
        formData.append('action', 'send');
        
        // Reset UI immediately
        messageInput.value = '';
        fileInput.value = '';
        handleFileSelect();
        
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
