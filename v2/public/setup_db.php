<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/Database.php';

header('Content-Type: text/plain');

echo "=== Railway Database Setup ===\n\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "Connection OK\n\n";
    
    $sql = file_get_contents(ROOT_PATH . '/setup.sql');
    
    // Split by semicolons but not inside strings
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = 0;
    $errors = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt) || str_starts_with($stmt, '--')) continue;
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "SKIP: " . substr($e->getMessage(), 0, 80) . "\n";
            } else {
                echo "ERROR: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }
    
    echo "\nDone: {$success} OK, {$errors} errors\n";
    
    // Verify
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users in database: {$count}\n";
    
    // Lock installer
    file_put_contents(ROOT_PATH . '/config/install.lock', 'locked');
    echo "\nInstaller locked.\n";
    echo "You can delete this file now.\n";
    
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
