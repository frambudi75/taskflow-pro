<?php
include 'config.php';
$tables = ['users', 'todos', 'notes', 'documents'];
foreach ($tables as $t) {
    echo "--- TABLE: $t ---\n";
    $stmt = $pdo->query("DESCRIBE $t");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "\n";
}
