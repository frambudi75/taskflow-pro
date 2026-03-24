<?php
// api_chat.php
require_once 'config.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$currentUser = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

if ($action === 'send') {
    $receiverId = (int)$_POST['receiver_id'];
    $message = trim($_POST['message']);
    
    if ($receiverId > 0 && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$currentUser, $receiverId, $message]);
        echo json_encode(['status' => 'success']);
    }
} elseif ($action === 'get') {
    $targetId = (int)$_GET['user_id'];
    
    // Mark as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$targetId, $currentUser]);
    
    // Fetch conversation
    $stmt = $pdo->prepare("
        SELECT id, sender_id, receiver_id, message, is_read, DATE_FORMAT(created_at, '%H:%i') as time 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$currentUser, $targetId, $targetId, $currentUser]);
    $messages = $stmt->fetchAll();
    
    echo json_encode($messages);
} elseif ($action === 'count_unread') {
    $count = countUnreadMessages($currentUser);
    echo json_encode(['unread_count' => $count]);
} elseif ($action === 'delete_msg') {
    $msgId = (int)$_POST['message_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$msgId, $currentUser]);
    echo json_encode(['status' => 'success']);
} elseif ($action === 'delete_all') {
    $targetId = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    $stmt->execute([$currentUser, $targetId, $targetId, $currentUser]);
    echo json_encode(['status' => 'success']);
}
?>
