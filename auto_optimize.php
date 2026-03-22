<?php
// auto_optimize.php - Automatic Database Optimization Script
// Usage: php auto_optimize.php
// Or schedule with cron: 0 2 * * * php /path/to/auto_optimize.php

require_once 'config.php';

// Only run if called from command line or by admin
if (!defined('STDIN') && !isAdmin()) {
    die("Access Denied");
}

echo "[TaskFlow Auto Optimize] Starting database maintenance...\n";

try {
    // Clear cache before optimization
    echo "- Clearing cache...\n";
    clearCache();
    
    $tables = ['users', 'todos', 'notes', 'documents'];
    $total_tables = count($tables);
    $optimized = 0;
    $indexed = 0;

    echo "- Processing $total_tables tables...\n";

    foreach ($tables as $table) {
        echo "  • Optimizing table: $table\n";

        // Analyze table
        $stmt = $pdo->prepare("ANALYZE TABLE $table");
        $stmt->execute();

        // Optimize table
        $stmt = $pdo->prepare("OPTIMIZE TABLE $table");
        $stmt->execute();

        $optimized++;

        // Check and create indexes if missing
        $indexes_to_check = [];
        switch ($table) {
            case 'users':
                $indexes_to_check = ['idx_users_email', 'idx_users_role'];
                break;
            case 'todos':
                $indexes_to_check = ['idx_todos_user_id', 'idx_todos_date', 'idx_todos_completed'];
                break;
            case 'notes':
                $indexes_to_check = ['idx_notes_user_id', 'idx_notes_updated'];
                break;
            case 'documents':
                $indexes_to_check = ['idx_documents_user_id', 'idx_documents_uploaded'];
                break;
        }

        foreach ($indexes_to_check as $index_name) {
            // Check if index exists
            $stmt = $pdo->prepare("SHOW INDEX FROM $table WHERE Key_name = ?");
            $stmt->execute([$index_name]);

            if ($stmt->rowCount() === 0) {
                // Create index based on name
                $create_sql = "";
                switch ($index_name) {
                    case 'idx_users_email':
                        $create_sql = "CREATE INDEX idx_users_email ON users(email)";
                        break;
                    case 'idx_users_role':
                        $create_sql = "CREATE INDEX idx_users_role ON users(role)";
                        break;
                    case 'idx_todos_user_id':
                        $create_sql = "CREATE INDEX idx_todos_user_id ON todos(user_id)";
                        break;
                    case 'idx_todos_date':
                        $create_sql = "CREATE INDEX idx_todos_date ON todos(todo_date)";
                        break;
                    case 'idx_todos_completed':
                        $create_sql = "CREATE INDEX idx_todos_completed ON todos(completed)";
                        break;
                    case 'idx_notes_user_id':
                        $create_sql = "CREATE INDEX idx_notes_user_id ON notes(user_id)";
                        break;
                    case 'idx_notes_updated':
                        $create_sql = "CREATE INDEX idx_notes_updated ON notes(updated_at)";
                        break;
                    case 'idx_documents_user_id':
                        $create_sql = "CREATE INDEX idx_documents_user_id ON documents(user_id)";
                        break;
                    case 'idx_documents_uploaded':
                        $create_sql = "CREATE INDEX idx_documents_uploaded ON documents(upload_date)";
                        break;
                }

                if (!empty($create_sql)) {
                    $pdo->exec($create_sql);
                    echo "    ✓ Created index: $index_name\n";
                    $indexed++;
                }
            }
        }
    }

    // Log the maintenance
    $log_message = "[" . date('Y-m-d H:i:s') . "] Auto optimization completed. Optimized: $optimized tables, Created: $indexed indexes\n";
    file_put_contents('logs/auto_optimize.log', $log_message, FILE_APPEND | LOCK_EX);

    echo "\n[TaskFlow Auto Optimize] Maintenance completed successfully!\n";
    echo "- Tables optimized: $optimized\n";
    echo "- Indexes created: $indexed\n";
    echo "- Log saved to: logs/auto_optimize.log\n";

} catch (Exception $e) {
    $error_message = "[" . date('Y-m-d H:i:s') . "] Auto optimization failed: " . $e->getMessage() . "\n";
    file_put_contents('logs/auto_optimize.log', $error_message, FILE_APPEND | LOCK_EX);

    echo "\n[TaskFlow Auto Optimize] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>