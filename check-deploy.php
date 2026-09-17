<?php
/**
 * DEPLOY CHECK — Upload this to your site root, visit it once, then DELETE it.
 * Shows whether config, .env, and database are all working.
 */
header('Content-Type: text/plain');
echo "=== DEPLOY CHECK ===\n\n";

// 1. Does the new config.php exist and load from .env?
$envPath = __DIR__ . '/v2/config/.env';
$configPath = __DIR__ . '/v2/config/config.php';

echo "[1] v2/config/.env exists:      " . (file_exists($envPath) ? "YES ✓" : "NO ✗ — upload v2/config/.env") . "\n";
echo "[2] v2/config/config.php exists: " . (file_exists($configPath) ? "YES ✓" : "NO ✗ — upload v2/config/config.php") . "\n";

if (file_exists($configPath)) {
    $src = file_get_contents($configPath);
    $hasEnvLoader = str_contains($src, '_secret');
    echo "[3] config.php loads from .env: " . ($hasEnvLoader ? "YES ✓" : "NO ✗ — still the OLD config.php") . "\n";
}

// 2. Does api/index.php use the config constants?
$apiPath = __DIR__ . '/api/index.php';
echo "[4] api/index.php exists:        " . (file_exists($apiPath) ? "YES ✓" : "NO ✗ — upload api/index.php") . "\n";

if (file_exists($apiPath)) {
    $apiSrc = file_get_contents($apiPath);
    $usesConfig = str_contains($apiSrc, "require_once ROOT_PATH . '/v2/config/config.php'");
    echo "[5] api/index.php loads config: " . ($usesConfig ? "YES ✓" : "NO ✗ — still the OLD api/index.php") . "\n";
}

// 3. Can we connect to the database?
echo "\n--- Database Connection ---\n";
define('ROOT_PATH', __DIR__ . '/v2');
if (file_exists($configPath)) {
    require_once $configPath;
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[6] DB connection:              SUCCESS ✓\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "[7] Tables found:               " . count($tables) . "\n";
        if (count($tables) > 0) {
            echo "    " . implode(', ', array_slice($tables, 0, 10)) . (count($tables) > 10 ? '...' : '') . "\n";
        }

        // Check users table
        if (in_array('users', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "[8] Users in database:          $count\n";
        }

    } catch (PDOException $e) {
        echo "[6] DB connection:              FAILED ✗\n";
        echo "    Error: " . $e->getMessage() . "\n";
        echo "\n    FIX: Go to cPanel → MySQL Databases\n";
        echo "    1. Check the exact MySQL username (must match DB_USER in .env)\n";
        echo "    2. Make sure the user has ALL PRIVILEGES on the database\n";
        echo "    3. Make sure the password matches what's in v2/config/.env\n";
    }
}

echo "\n=== DONE ===\n";
echo "DELETE this file (check-deploy.php) after verifying.\n";
