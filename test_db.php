<?php
define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/v2/config/config.php';

header('Content-Type: text/plain');

echo "=== DATABASE TEST ===\n\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_PASS: " . (DB_PASS === '' ? '(EMPTY!)' : '***set***') . "\n";

echo "\n--- Connection ---\n";
try {
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s;port=%s", DB_HOST, DB_NAME, DB_CHARSET, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "Connection: OK\n";

    echo "\n--- Users ---\n";
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Total users: {$count}\n";

    $users = $pdo->query("SELECT id, fullname, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "  ID={$u['id']} | {$u['fullname']} | {$u['email']} | {$u['role']} | {$u['status']} | {$u['created_at']}\n";
    }

    echo "\n--- Test INSERT ---\n";
    $testEmail = 'test_' . time() . '@test.com';
    $hash = password_hash('Test@12345', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("INSERT INTO users (fullname, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, 'user', 'active', 1)")
        ->execute(['Test User', $testEmail, $hash]);
    $id = $pdo->lastInsertId();
    echo "Inserted test user ID: {$id}\n";

    $found = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $found->execute([$id]);
    $row = $found->fetch(PDO::FETCH_ASSOC);
    echo "Verified: " . ($row ? "YES - found in DB" : "NO - NOT found!") . "\n";

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    echo "Cleaned up test user.\n";

} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== DONE - DELETE THIS FILE ===\n";
