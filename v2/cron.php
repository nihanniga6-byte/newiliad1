<?php
/**
 * Cron Job Handler
 * 
 * Token-protected script for scheduled tasks:
 * - Session cleanup
 * - Log rotation
 * - Cache refresh
 * - Password reset cleanup
 * 
 * Access via: /cron.php?token=YOUR_TOKEN
 * 
 * @package System
 */

// Security: Only allow CLI or token access
$isCLI = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';

// Load config
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die('System not installed');
}

require_once $configFile;

// Verify token (skip in CLI mode)
if (!$isCLI) {
    if (!defined('CRON_SECRET') || $token !== CRON_SECRET) {
        http_response_code(403);
        die('Invalid token');
    }
}

// Load core files
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Cache.php';
require_once __DIR__ . '/core/Logger.php';

// Initialize
$db = Database::getInstance();

// Output header for CLI
if ($isCLI) {
    echo "=== Cron Job Started: " . date('Y-m-d H:i:s') . " ===\n\n";
}

// =============================================
// 1. Session Cleanup
// =============================================
if ($isCLI) echo "1. Cleaning up expired sessions...\n";

try {
    $maxLifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 1800;
    $cutoff = time() - $maxLifetime;
    
    $stmt = $db->query("DELETE FROM sessions WHERE last_activity < :cutoff", ['cutoff' => $cutoff]);
    $deletedSessions = $stmt->rowCount();
    
    if ($isCLI) echo "   Deleted {$deletedSessions} expired sessions\n";
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// =============================================
// 2. Password Reset Cleanup
// =============================================
if ($isCLI) echo "\n2. Cleaning up expired password resets...\n";

try {
    $stmt = $db->query("DELETE FROM password_resets WHERE expires_at < NOW()");
    $deletedResets = $stmt->rowCount();
    
    if ($isCLI) echo "   Deleted {$deletedResets} expired password resets\n";
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// =============================================
// 3. Log Rotation
// =============================================
if ($isCLI) echo "\n3. Checking log rotation...\n";

try {
    $logFile = LOG_PATH . '/error.log';
    $maxSize = defined('LOG_MAX_SIZE') ? LOG_MAX_SIZE : 5 * 1024 * 1024;
    
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        $archiveName = LOG_PATH . '/error_' . date('Y-m-d_His') . '.log';
        rename($logFile, $archiveName);
        file_put_contents($logFile, '');
        
        if ($isCLI) echo "   Log rotated: {$archiveName}\n";
    } else {
        if ($isCLI) echo "   No rotation needed\n";
    }
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// =============================================
// 4. Cache Cleanup
// =============================================
if ($isCLI) echo "\n4. Cleaning up expired cache files...\n";

try {
    $cache = new Cache();
    $cache->gc();
    
    if ($isCLI) echo "   Cache garbage collection completed\n";
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// =============================================
// 5. Activity Log Cleanup (keep 90 days)
// =============================================
if ($isCLI) echo "\n5. Cleaning up old activity logs...\n";

try {
    $stmt = $db->query("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $deletedLogs = $stmt->rowCount();
    
    if ($isCLI) echo "   Deleted {$deletedLogs} old activity logs\n";
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// =============================================
// 6. Verification Token Cleanup
// =============================================
if ($isCLI) echo "\n6. Cleaning up unverified users (older than 24 hours)...\n";

try {
    $stmt = $db->query("DELETE FROM users WHERE email_verified = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $deletedUsers = $stmt->rowCount();
    
    if ($isCLI) echo "   Deleted {$deletedUsers} unverified users\n";
} catch (Exception $e) {
    if ($isCLI) echo "   Error: " . $e->getMessage() . "\n";
}

// Output summary
if ($isCLI) {
    echo "\n=== Cron Job Completed: " . date('Y-m-d H:i:s') . " ===\n";
    echo "Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    echo "Peak memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cron job completed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
