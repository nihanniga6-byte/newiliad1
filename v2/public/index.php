<?php
/**
 * Front Controller - Single Entry Point
 * 
 * All HTTP requests are routed through this file.
 * This is the ONLY file that should be directly accessible.
 * 
 * @package Core
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php' && 
    basename($_SERVER['SCRIPT_NAME']) === '/index.php' &&
    !isset($_SERVER['REQUEST_URI'])) {
    exit('Direct access not permitted');
}

// Define root path (works in subfolders too)
define('ROOT_PATH', dirname(__DIR__));

// Check if config exists, if not redirect to installer
if (!file_exists(ROOT_PATH . '/config/config.php')) {
    header('Location: /install.php');
    exit;
}

// Check if installer lock exists
if (file_exists(ROOT_PATH . '/config/install.lock') && 
    basename($_SERVER['REQUEST_URI']) === '/install.php') {
    header('Location: /');
    exit;
}

// Load configuration
require_once ROOT_PATH . '/config/config.php';

// Set error/exception handlers
require_once ROOT_PATH . '/core/ErrorHandler.php';
ErrorHandler::init();

// Initialize session
require_once ROOT_PATH . '/core/Session.php';
Session::init();

// Autoloader
require_once ROOT_PATH . '/core/Autoloader.php';

// Load core classes
require_once ROOT_PATH . '/core/Database.php';

// Auto-create tables if they don't exist
if (!file_exists(ROOT_PATH . '/config/install.lock')) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$check) {
            $sql = file_get_contents(ROOT_PATH . '/setup.sql');
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt) && strpos($stmt, '--') !== 0) {
                    try { $pdo->exec($stmt); } catch (Exception $e) {}
                }
            }
        }
        file_put_contents(ROOT_PATH . '/config/install.lock', 'auto');
    } catch (Exception $e) {}
}

require_once ROOT_PATH . '/core/Router.php';
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/core/Security.php';
require_once ROOT_PATH . '/core/Auth.php';
require_once ROOT_PATH . '/core/Cache.php';
require_once ROOT_PATH . '/core/Logger.php';
require_once ROOT_PATH . '/core/ActivityLogger.php';
require_once ROOT_PATH . '/core/Validation.php';
require_once ROOT_PATH . '/core/FileHandler.php';

// Load helpers
require_once ROOT_PATH . '/app/helpers/functions.php';

// Initialize Router
$router = new Router();

// ==================
// Public Routes
// ==================
$router->get('/', 'HomeController@index');
$router->get('/about', 'HomeController@about');
$router->get('/services', 'HomeController@services');
$router->get('/contact', 'HomeController@contact');

// ==================
// Auth Routes
// ==================
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@registerForm');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@forgotPasswordForm');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password', 'AuthController@resetPasswordForm');
$router->post('/reset-password', 'AuthController@resetPassword');

// ==================
// Dashboard Routes (Auth Required)
// ==================
$router->get('/dashboard', 'DashboardController@index', ['auth']);
$router->get('/dashboard/profile', 'DashboardController@profile', ['auth']);
$router->post('/dashboard/profile', 'DashboardController@updateProfile', ['auth', 'csrf']);
$router->get('/dashboard/password', 'DashboardController@password', ['auth']);
$router->post('/dashboard/password', 'DashboardController@changePassword', ['auth', 'csrf']);

// ==================
// Admin Routes (Admin Required)
// ==================
$router->get('/admin', 'Admin\DashboardController@index', ['auth', 'admin']);
$router->get('/admin/users', 'Admin\UserController@index', ['auth', 'admin']);
$router->get('/admin/users/create', 'Admin\UserController@create', ['auth', 'admin']);
$router->post('/admin/users/store', 'Admin\UserController@store', ['auth', 'admin', 'csrf']);
$router->get('/admin/users/edit/{id}', 'Admin\UserController@edit', ['auth', 'admin']);
$router->post('/admin/users/update', 'Admin\UserController@update', ['auth', 'admin', 'csrf']);
$router->post('/admin/users/delete', 'Admin\UserController@delete', ['auth', 'admin', 'csrf']);
$router->post('/admin/users/toggle-status', 'Admin\UserController@toggleStatus', ['auth', 'admin', 'csrf']);
$router->get('/admin/settings', 'Admin\SettingsController@index', ['auth', 'admin']);
$router->post('/admin/settings', 'Admin\SettingsController@update', ['auth', 'admin', 'csrf']);

// ==================
// File Download Route
// ==================
$router->get('/download', 'DownloadController@index', ['auth']);

// ==================
// API Routes (AJAX)
// ==================
$router->get('/api/stats', 'Api\StatsController@index', ['auth', 'admin']);

// ==================
// Debug Route (DELETE AFTER TESTING)
// ==================
$router->get('/test-register', function() {
    header('Content-Type: text/plain');
    
    echo "=== DATABASE TEST ===\n\n";
    
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "DB_PORT: " . DB_PORT . "\n\n";
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        echo "Connection: OK\n\n";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Users in DB: {$count}\n";
        
        $users = $pdo->query("SELECT id, fullname, email, role, status FROM users ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            echo "  #{$u['id']} {$u['email']} ({$u['role']}, {$u['status']})\n";
        }
        
        // Try register + login test
        echo "\n--- Register Test ---\n";
        $testEmail = 'debug_' . time() . '@test.com';
        $testPass = 'Debug@12345';
        $hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $pdo->prepare("INSERT INTO users (fullname, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, 'user', 'active', 1)")
            ->execute(['Debug User', $testEmail, $hash]);
        $id = $pdo->lastInsertId();
        echo "Inserted user ID: {$id}\n";
        
        // Now try login the same way Auth::login does
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND status = 'active'");
        $stmt->execute(['email' => $testEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "User found by email: YES\n";
            $verified = password_verify($testPass, $user['password_hash']);
            echo "password_verify result: " . ($verified ? 'PASS' : 'FAIL') . "\n";
        } else {
            echo "User found by email: NO\n";
        }
        
        // Cleanup
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo "\nTest user deleted.\n";
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DONE - DELETE THIS ROUTE AFTER TESTING ===\n";
    exit;
});

// Dispatch the request
$router->dispatch();
