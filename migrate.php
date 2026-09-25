<?php
// migrate.php - Run this once to update DB structure
include 'config.php';

if (!isAdmin()) {
    die("Akses ditolak. Hanya administrator yang dapat menjalankan migrasi.");
}

try {
    // 1. Add status to todos
    $pdo->exec("ALTER TABLE todos ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'todo' AFTER completed");
    // Sync completed status to status column for existing records
    $pdo->exec("UPDATE todos SET status = 'done' WHERE completed = 1 AND status = 'todo'");
    
    // 2. Create activities table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255),
        target_type VARCHAR(50),
        target_id INT,
        metadata TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 4. Create document_shares table
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        shared_by_user_id INT NOT NULL,
        permission VARCHAR(20) DEFAULT 'view',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_doc_share (document_id, shared_with_user_id)
    )");

    // 5. Create daily_reports table
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_date DATE NOT NULL,
        category VARCHAR(50) DEFAULT 'Umum',
        start_time TIME NULL,
        end_time TIME NULL,
        supervisor_name VARCHAR(100) NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        photo_path TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_report_date (user_id, report_date)
    )");

    try { $pdo->exec("ALTER TABLE daily_reports MODIFY COLUMN photo_path TEXT NULL"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN category VARCHAR(50) DEFAULT 'Umum' AFTER report_date"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN start_time TIME NULL AFTER category"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN end_time TIME NULL AFTER start_time"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN supervisor_name VARCHAR(100) NULL AFTER end_time"); } catch (\Throwable $e) {}

    // Initial settings
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
    $stmt->execute(['app_name', 'TaskFlow Pro']);
    $stmt->execute(['app_logo', '']);
    $stmt->execute(['allow_registration', '1']);

    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'>
            <h1 style='color:#10b981'>✅ Migrasi Berhasil!</h1>
            <p>Struktur database telah diperbarui.</p>
            <a href='index.php?page=dashboard' style='color:#6366f1; font-weight:bold; text-decoration:none;'>Kembali ke Dashboard →</a>
          </div>";

} catch (Exception $e) {
    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'>
            <h1 style='color:#ef4444'>❌ Migrasi Gagal</h1>
            <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}
