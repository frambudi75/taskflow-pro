<?php
require_once 'config.php';

try {
    $settings = [
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encrypt' => 'tls',
        'smtp_from' => ''
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }
    echo "SMTP settings initialized successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
