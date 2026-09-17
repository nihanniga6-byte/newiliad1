<?php
/**
 * Installation Wizard
 * 
 * Sets up database, creates tables, and creates admin user.
 * Auto-disables after successful installation.
 * 
 * @package Installation
 */

// Security: Check if already installed
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
    if (defined('INSTALLED') && INSTALLED === true) {
        die('System is already installed. Delete install.lock to reinstall.');
    }
}

// Start session
session_start();

// Set page title
$pageTitle = 'نصب سیستم';

// Installation steps
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);
    
    switch ($step) {
        case 2: // Database configuration
            $host = trim($_POST['db_host'] ?? '');
            $name = trim($_POST['db_name'] ?? '');
            $user = trim($_POST['db_user'] ?? '');
            $pass = $_POST['db_pass'] ?? '';
            $prefix = trim($_POST['db_prefix'] ?? 'z_');
            
            // Validate
            if (empty($host) || empty($name) || empty($user)) {
                $error = 'Please fill in all database fields';
                $step = 1;
                break;
            }
            
            // Test connection
            try {
                $dsn = "mysql:host={$host};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Create database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$name}`");
                
                // Store in session
                $_SESSION['install'] = [
                    'host' => $host,
                    'name' => $name,
                    'user' => $user,
                    'pass' => $pass,
                    'prefix' => $prefix
                ];
                
                $success = 'Database connection successful!';
                $step = 2;
            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
                $step = 1;
            }
            break;
            
        case 3: // Create tables
            if (!isset($_SESSION['install'])) {
                $error = 'Session expired. Please go back.';
                $step = 1;
                break;
            }
            
            $install = $_SESSION['install'];
            
            try {
                $dsn = "mysql:host={$install['host']};dbname={$install['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $install['user'], $install['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Read and execute SQL file
                $sql = file_get_contents(__DIR__ . '/setup.sql');
                
                // Replace table prefix if needed
                if ($install['prefix'] !== 'z_') {
                    $sql = str_replace('z_', $install['prefix'], $sql);
                }
                
                // Execute SQL statements
                $statements = explode(';', $sql);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                $success = 'Database tables created successfully!';
                $step = 3;
            } catch (PDOException $e) {
                $error = 'Error creating tables: ' . $e->getMessage();
                $step = 2;
            }
            break;
            
        case 4: // Create admin user
            if (!isset($_SESSION['install'])) {
                $error = 'Session expired. Please go back.';
                $step = 1;
                break;
            }
            
            $fullname = trim($_POST['admin_name'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $password = $_POST['admin_pass'] ?? '';
            $confirm = $_POST['admin_pass_confirm'] ?? '';
            
            // Validate
            if (empty($fullname) || empty($email) || empty($password)) {
                $error = 'Please fill in all fields';
                $step = 3;
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
                $step = 3;
                break;
            }
            
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters';
                $step = 3;
                break;
            }
            
            if ($password !== $confirm) {
                $error = 'Passwords do not match';
                $step = 3;
                break;
            }
            
            $install = $_SESSION['install'];
            
            try {
                $dsn = "mysql:host={$install['host']};dbname={$install['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $install['user'], $install['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Check if admin email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Admin email already exists';
                    $step = 3;
                    break;
                }
                
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Insert admin user
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, 'admin', 'active', 1)");
                $stmt->execute([$fullname, $email, $passwordHash]);
                
                // Create empty profile
                $userId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
                $stmt->execute([$userId]);
                
                $success = 'Admin user created successfully!';
                $step = 4;
            } catch (PDOException $e) {
                $error = 'Error creating admin user: ' . $e->getMessage();
                $step = 3;
            }
            break;
            
        case 5: // Create config and finish
            if (!isset($_SESSION['install'])) {
                $error = 'Session expired. Please go back.';
                $step = 1;
                break;
            }
            
            $install = $_SESSION['install'];
            
            // Generate encryption key
            $encryptionKey = bin2hex(random_bytes(32));
            $cronSecret = bin2hex(random_bytes(16));
            
            // Secrets go into the gitignored .env file — never into config.php
            $envContent = <<<ENV
# Secrets — this file is gitignored and never committed.
DB_HOST={$install['host']}
DB_NAME={$install['name']}
DB_USER={$install['user']}
DB_PASS={$install['pass']}
SECURITY_KEY={$encryptionKey}
CRON_SECRET={$cronSecret}
ENV;
            
            // Create config file (contains no secrets — they are loaded from .env)
            $configContent = <<<'CFG'
<?php
/**
 * Configuration File
 * Generated by Installation Wizard
 * Secrets are read from the gitignored `.env` file in this same folder.
 * 
 * @package Config
 */

// Prevent direct access
if (!defined('ROOT_PATH')) {
    exit('Direct access not permitted');
}

// ==================
// Load Secrets from .env (gitignored — never commit secrets)
// ==================
$_envFile = __DIR__ . '/.env';
$_env = [];
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#' || strpos($_line, '=') === false) {
            continue;
        }
        [$_key, $_value] = explode('=', $_line, 2);
        $_key = trim($_key);
        $_value = trim($_value);
        $_len = strlen($_value);
        if ($_len >= 2 && (($_value[0] === '"' && $_value[$_len - 1] === '"') || ($_value[0] === "'" && $_value[$_len - 1] === "'"))) {
            $_value = substr($_value, 1, -1);
        }
        $_env[$_key] = $_value;
        putenv("{$_key}={$_value}");
        $_ENV[$_key] = $_value;
    }
}

// Read a secret from the environment, exiting with a clear message if missing
$_secret = function (string $key): string {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? '';
    }
    if ($value === '') {
        exit("Configuration error: missing {$key}. Add it to " . basename(__DIR__) . "/.env (copy .env.example first).");
    }
    return $value;
};

// Environment
define('APP_ENV', 'production');

// Database
define('DB_HOST', $_secret('DB_HOST'));
define('DB_NAME', $_secret('DB_NAME'));
define('DB_USER', $_secret('DB_USER'));
define('DB_PASS', $_secret('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// Security
define('SECURITY_KEY', $_secret('SECURITY_KEY'));
define('CRON_SECRET', $_secret('CRON_SECRET'));
define('BCRYPT_COST', 12);
define('CSRF_TOKEN_LIFETIME', 3600);
define('SESSION_TIMEOUT', 1800);
define('REMEMBER_ME_DAYS', 30);

// Paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('LOG_PATH', ROOT_PATH . '/logs');

// File Uploads
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);

// URLs
define('APP_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('APP_NAME', 'Dr. Zohrabi Nutrition Clinic');

// Cache
define('CACHE_LIFETIME', 3600);
define('PER_PAGE', 50);

// Error Log
define('LOG_MAX_SIZE', 5 * 1024 * 1024); // 5MB

// Timezone
date_default_timezone_set('Asia/Tehran');

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/error.log');
}
CFG;
            
            $configPath = __DIR__ . '/config/config.php';
            
            // Create config directory if not exists
            if (!is_dir(__DIR__ . '/config')) {
                mkdir(__DIR__ . '/config', 0755, true);
            }
            
            if (file_put_contents($configPath, $configContent) === false) {
                $error = 'Failed to create config file. Please check directory permissions.';
                $step = 4;
                break;
            }
            
            // Write secrets to .env (gitignored, never committed)
            $envPath = __DIR__ . '/config/.env';
            if (file_put_contents($envPath, $envContent) === false) {
                $error = 'Failed to create .env file. Please check directory permissions.';
                $step = 4;
                break;
            }
            @chmod($envPath, 0600);
            
            // Create required directories
            $dirs = ['uploads', 'uploads/avatars', 'cache', 'logs'];
            foreach ($dirs as $dir) {
                $dirPath = __DIR__ . '/' . $dir;
                if (!is_dir($dirPath)) {
                    mkdir($dirPath, 0755, true);
                }
            }
            
            // Create install.lock
            file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'));
            
            // Clear session
            session_unset();
            session_destroy();
            
            $success = 'Installation completed successfully!';
            $step = 5;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f1f5f9; min-height: 100vh; }
        .install-container { max-width: 600px; margin: 50px auto; padding: 0 20px; }
        .install-card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .install-header { text-align: center; margin-bottom: 30px; }
        .install-header h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .install-header p { color: #64748b; }
        .steps { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                background: #e2e8f0; color: #64748b; font-weight: 600; margin: 0 8px; }
        .step.active { background: #16a34a; color: white; }
        .step.completed { background: #22c55e; color: white; }
        .form-label { font-weight: 600; }
        .btn-primary { background: #16a34a; border-color: #16a34a; }
        .btn-primary:hover { background: #15803d; border-color: #15803d; }
        .alert { border-radius: 8px; }
        .success-box { text-align: center; padding: 30px; }
        .success-box i { font-size: 4rem; color: #16a34a; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="bi bi-heart-pulse"></i> Dr. Zohrabi Nutrition Clinic</h1>
                <p>Installation Wizard</p>
            </div>

            <!-- Steps -->
            <div class="steps">
                <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1</div>
                <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2</div>
                <div class="step <?= $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' ?>">3</div>
                <div class="step <?= $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' ?>">4</div>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success && $step < 5): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Step 1: Requirements -->
            <?php if ($step === 1): ?>
            <h4 class="mb-3">Step 1: Server Requirements</h4>
            <div class="mb-4">
                <h6>Checking requirements...</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <?php if (version_compare(PHP_VERSION, '8.1.0', '>=')): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                        PHP <?= PHP_VERSION ?> (required: 8.1+)
                    </li>
                    <li class="mb-2">
                        <?php if (extension_loaded('pdo_mysql')): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                        PDO MySQL Extension
                    </li>
                    <li class="mb-2">
                        <?php if (extension_loaded('mbstring')): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                        MBString Extension
                    </li>
                    <li class="mb-2">
                        <?php if (is_writable(__DIR__ . '/config') || is_writable(__DIR__)): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                        Config Directory Writable
                    </li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <div class="mb-3">
                    <label class="form-label">Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="zohrabi_clinic" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database User</label>
                    <input type="text" name="db_user" class="form-control" placeholder="e.g. h421704" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Password</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Table Prefix</label>
                    <input type="text" name="db_prefix" class="form-control" value="z_">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-arrow-left"></i> Continue
                </button>
            </form>

            <!-- Step 2: Test Connection -->
            <?php elseif ($step === 2): ?>
            <h4 class="mb-3">Step 2: Database Setup</h4>
            <form method="POST">
                <input type="hidden" name="step" value="3">
                <p>Click below to create the database tables.</p>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-database"></i> Create Tables
                </button>
            </form>

            <!-- Step 3: Create Admin -->
            <?php elseif ($step === 3): ?>
            <h4 class="mb-3">Step 3: Create Admin User</h4>
            <form method="POST">
                <input type="hidden" name="step" value="4">
                <div class="mb-3">
                    <label class="form-label">Admin Full Name</label>
                    <input type="text" name="admin_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="admin_pass" class="form-control" minlength="8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="admin_pass_confirm" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus"></i> Create Admin User
                </button>
            </form>

            <!-- Step 4: Finalize -->
            <?php elseif ($step === 4): ?>
            <h4 class="mb-3">Step 4: Finalize Installation</h4>
            <form method="POST">
                <input type="hidden" name="step" value="5">
                <p>Click below to complete the installation and create the configuration file.</p>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg"></i> Complete Installation
                </button>
            </form>

            <!-- Step 5: Success -->
            <?php elseif ($step === 5): ?>
            <div class="success-box">
                <i class="bi bi-check-circle-fill"></i>
                <h3>Installation Complete!</h3>
                <p class="text-muted mb-4">The system has been installed successfully.</p>
                <div class="d-grid gap-2">
                    <a href="/" class="btn btn-primary">
                        <i class="bi bi-house"></i> Go to Homepage
                    </a>
                    <a href="/login" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-in-left"></i> Login to Admin
                    </a>
                </div>
                <div class="mt-4 p-3 bg-light rounded">
                    <small class="text-muted">
                        <strong>Important:</strong> For security, please delete the <code>install.php</code> file after installation.
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
