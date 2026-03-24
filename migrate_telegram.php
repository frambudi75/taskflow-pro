<?php
require_once 'config.php';

try {
    // Add telegram_chat_id to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(100) DEFAULT NULL AFTER profile_photo");
    echo "Added telegram_chat_id column to users table.\n";
    
    // Add telegram_bot_token to settings table
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES ('telegram_bot_token', '')");
    $stmt->execute();
    echo "Initialized telegram_bot_token in settings table.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>
