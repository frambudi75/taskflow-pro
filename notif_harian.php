<?php
require_once 'config.php';

$isTest = isset($_GET['test']) && $_GET['test'] == '1';
$dayOfWeek = date('N'); // 1 (Mon) to 7 (Sun)
$isWorkingDay = ($dayOfWeek >= 1 && $dayOfWeek <= 5);

if (!$isWorkingDay && !$isTest) {
    echo "Hari ini bukan hari kerja. Gunakan ?test=1 untuk memaksa pengiriman.\n";
    exit;
}

$today = date('Y-m-d');
$dayNameArr = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$displayDay = $dayNameArr[$dayOfWeek];

// Fetch all users with Telegram Chat ID
$stmt = $pdo->query("SELECT id, display_name, telegram_chat_id FROM users WHERE telegram_chat_id IS NOT NULL AND telegram_chat_id != ''");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    // Fetch tasks for today
    $taskStmt = $pdo->prepare("SELECT title, status FROM todos WHERE user_id = ? AND todo_date = ? AND status != 'done'");
    $taskStmt->execute([$user['id'], $today]);
    $tasks = $taskStmt->fetchAll();

    $taskCount = count($tasks);
    
    $message = "<b>📅 Notifikasi Harian (" . $displayDay . ")</b>\n";
    $message .= "Halo <b>" . $user['display_name'] . "</b>, selamat bekerja!\n\n";
    
    if ($taskCount > 0) {
        $message .= "Anda memiliki <b>" . $taskCount . " tugas</b> yang perlu diselesaikan hari ini:\n";
        foreach ($tasks as $i => $task) {
            $statusIcon = ($task['status'] == 'in_progress') ? '⏳' : '📝';
            $message .= ($i+1) . ". " . $statusIcon . " " . htmlspecialchars($task['title']) . "\n";
        }
    } else {
        $message .= "Yay! Tidak ada tugas mendesak untuk hari ini. Tetap produktif!\n";
    }

    $message .= "\nBuka aplikasi: " . baseUrl('dashboard');

    if (sendTelegramNotif($user['telegram_chat_id'], $message)) {
        echo "✅ Berhasil mengirim notifikasi ke: " . $user['display_name'] . " (" . $user['telegram_chat_id'] . ")\n";
    } else {
        echo "❌ Gagal mengirim notifikasi ke: " . $user['display_name'] . " (Cek Bot Token atau Chat ID)\n";
    }
}
?>
