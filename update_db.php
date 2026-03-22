<?php
// Run this script after starting MySQL to migrate database schema.
require_once 'config.php';

$messages = [];

// 1) Add file_path column to documents table (existing migration)
try {
    $pdo->exec('ALTER TABLE documents ADD COLUMN file_path VARCHAR(255) AFTER name');
    $messages[] = 'Column file_path added successfully to documents table.';
} catch (Exception $e) {
    $messages[] = 'Documents table: ' . $e->getMessage();
}

// 2) Add profile_photo column to users table (for profile image upload)
try {
    $pdo->exec('ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role');
    $messages[] = 'Column profile_photo added successfully to users table.';
} catch (Exception $e) {
    $messages[] = 'Users table: ' . $e->getMessage();
}

foreach ($messages as $msg) {
    echo $msg . "\n";
}

?>