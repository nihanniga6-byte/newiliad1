<?php
header('Content-Type: text/plain');
define('ROOT_PATH', dirname(__FILE__) . '/v2');
$configPath = ROOT_PATH . '/config/config.php';
$envPath = ROOT_PATH . '/config/.env';

echo "=== SERVER DIAGNOSTIC ===\n\n";
echo "1. config.php path: $configPath\n";
echo "   exists: " . (file_exists($configPath) ? 'YES' : 'NO') . "\n";
echo "   size: " . (file_exists($configPath) ? filesize($configPath) : 0) . " bytes\n";

// Check what the raw file contains (before PHP processes it)
if (file_exists($configPath)) {
    $raw = file_get_contents($configPath);
    if (preg_match("/define\('DB_USER'.*?\)/", $raw, $m)) {
        echo "   DB_USER line in file: " . trim($m[0]) . "\n";
    }
    if (preg_match("/define\('DB_PASS'.*?\)/", $raw, $m)) {
        echo "   DB_PASS line in file: " . trim($m[0]) . "\n";
    }
    if (str_contains($raw, 'h421704_clinic')) {
        echo "   contains h421704_clinic: YES ✓\n";
    } else {
        echo "   contains h421704_clinic: NO ✗ (OLD FILE!)\n";
    }
    if (str_contains($raw, 'h421704_clinic') && !str_contains($raw, '_env')) {
        echo "   WARNING: This looks like the OLD config.php (hardcoded values, no .env loader)\n";
    }
    if (str_contains($raw, '_env') || str_contains($raw, '_secret')) {
        echo "   looks like NEW config (has .env loader): YES ✓\n";
    }
}

echo "\n2. .env file: $envPath\n";
echo "   exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n";
if (file_exists($envPath)) {
    echo "   size: " . filesize($envPath) . " bytes\n";
    $envContent = file_get_contents($envPath);
    if (preg_match('/^DB_USER=(.+)$/m', $envContent, $m)) {
        echo "   DB_USER in .env: " . $m[1] . "\n";
    }
    if (preg_match('/^DB_PASS=(.+)$/m', $envContent, $m)) {
        echo "   DB_PASS in .env: " . $m[1] . "\n";
    }
}

echo "\n3. PHP version: " . phpversion() . "\n";
echo "4. OPcache: " . (function_exists('opcache_get_status') ? (json_encode(@opcache_get_status()) ?: 'enabled but status unavailable') : 'not available') . "\n";

echo "\n=== DONE — delete this file after checking ===\n";
