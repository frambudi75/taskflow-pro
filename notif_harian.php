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

// Fetch all users with Telegram Chat ID or active email
$stmt = $pdo->query("SELECT id, email, display_name, telegram_chat_id FROM users");
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
        echo "✅ Telegram sent: " . $user['display_name'] . "\n";
    }

    // --- Sent Email Notif ---
    $subject = "📅 TaskFlow: Daftar Tugas Harian - " . $displayDay;
    // Prepare HTML/Text message for email
    $emailBody = "Halo <b>" . $user['display_name'] . "</b>, berikut adalah tugas hari ini:\n\n";
    if ($taskCount > 0) {
        foreach ($tasks as $i => $task) {
            $emailBody .= ($i+1) . ". " . htmlspecialchars($task['title']) . " (" . $task['status'] . ")\n";
        }
    } else {
        $emailBody .= "Tidak ada tugas mendesak hari ini.";
    }
    $emailBody .= "\n\nBuka dashboard: " . baseUrl('dashboard');

    if (sendEmailNotif($user['email'] ?? '', $subject, $emailBody)) {
        echo "📧 Email sent: " . ($user['email'] ?? 'undefined') . "\n";
    } else {
        echo "❌ Email failed: " . ($user['email'] ?? 'undefined') . "\n";
    }
}
?>
