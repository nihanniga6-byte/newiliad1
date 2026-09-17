<?php
/**
 * Clinic API - Single entry point for all data operations
 * Replaces localStorage with MySQL database for cross-device sync
 */

// One session across apex + www: the session cookie is host-only by default,
// so a login on zohrabiclinic.ir is invisible on www.zohrabiclinic.ir (and
// vice versa) and auth/me 401s. Share it via a parent domain cookie — but
// ONLY on production hosts; localhost/test must keep the default or sessions
// break there entirely.
$__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
if (preg_match('/(^|\.)zohrabiclinic\.ir$/', $__host)) {
    ini_set('session.cookie_domain', '.zohrabiclinic.ir');
}
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
session_start();

header('Content-Type: application/json; charset=utf-8');
$__origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($__origin !== '') {
    header('Access-Control-Allow-Origin: ' . $__origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load DB credentials from v2 config
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/v2/config/config.php';

// DB connection
try {
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4;port=%s", DB_HOST, DB_NAME, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Database connection failed: ' . $e->getMessage());
}

// Column name mapping: JS camelCase ↔ DB snake_case
// The frontend JS uses camelCase keys (patientPhone, from, to, etc.)
// but the MySQL tables use snake_case columns (patient_phone, from_phone, etc.)
// NOTE: these MUST be defined before the routing switch below, because the
// switch dispatches (and exits) before any later top-level code would run.
$JS_TO_DB_MAP = [
    'patientPhone'  => 'patient_phone',
    'patientName'   => 'patient_name',
    'createdAt'     => 'created_at',
    'createdAtTime' => 'created_at_time',
    'from'          => 'from_phone',
    'to'            => 'to_phone',
    'desc'          => 'description',
    'doctorNote'    => 'doctor_note',
    'read'          => 'is_read',
];
$DB_TO_JS_MAP = array_flip($JS_TO_DB_MAP);

// Keys that exist only in JS (not real DB columns)
// NOTE: `read` on messages IS persisted (mapped to `is_read`), so it is
// intentionally NOT in this list. `patientId` on plans is a transient DOM
// id; plans sync by patientPhone instead.
$JS_ONLY_KEYS = ['patientId'];
// No tables skip the JS id: every record carries a client-generated id
// (Date.now()) so upserts dedupe across devices instead of inserting duplicates.
$AUTO_INCREMENT_TABLES = [];

// Bump SCHEMA_VERSION whenever setupTables() gains a table/migration.
define('SCHEMA_VERSION', 4);
// Auto-create tables (once per session per schema version — every boot fires
// ~10 concurrent API calls and re-running DDL per call needlessly serializes
// them on the session lock)
if (($_SESSION['clinic_schema_v'] ?? 0) !== SCHEMA_VERSION) {
    setupTables($pdo);
    $_SESSION['clinic_schema_v'] = SCHEMA_VERSION;
}

// Route requests via $_GET['action'] (works without .htaccess rewrite)
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Debug logging
error_log("[API] action={$action} method={$method}");

switch ($action) {
    case 'auth/me':
        authMe();
        break;
    case 'auth/login':
        authLogin($input, $pdo);
        break;
    case 'auth/register':
        authRegister($input, $pdo);
        break;
    case 'auth/logout':
        authLogout();
        break;
    case 'patients':
        handleCrud('clinic_users', $method, $input, $pdo, false);
        break;
    case 'patients/delete':
        // POST fallback for hosts that strip DELETE bodies
        handleCrud('clinic_users', 'DELETE', $input, $pdo, false);
        break;
    case 'appointments':
        handleCrud('clinic_appointments', $method, $input, $pdo, false);
        break;
    case 'mealplans':
        handleCrud('clinic_mealplans', $method, $input, $pdo, false);
        break;
    case 'mealplans/delete':
        handleDeleteById('clinic_mealplans', $input, $pdo);
        break;
    case 'plans/delete':
        handleDeleteById('clinic_explans', $input, $pdo);
        break;
    case 'plans':
        handleCrud('clinic_explans', $method, $input, $pdo, false);
        break;
    case 'messages':
        handleCrud('clinic_messages', $method, $input, $pdo, false);
        break;
    case 'tests':
        handleCrud('clinic_tests', $method, $input, $pdo, true);
        break;
        case 'progress':
            handleCrud('clinic_progress', $method, $input, $pdo, true);
            break;
        case 'water':
            handleWater($method, $input, $pdo);
            break;
        case 'water/delete':
            handleWater('DELETE', $input, $pdo);
            break;
        case 'notifications':
            handleNotifications($method, $input, $pdo);
            break;
        case 'debug':
        handleDebug($pdo);
        break;
    case 'clear-cache':
        // Clear OPcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        jsonResponse(true, 'Cache cleared');
        break;
    default:
        jsonResponse(false, 'Unknown endpoint: ' . $action, null, 404);
}

// ==================== FUNCTIONS ====================

function jsonResponse($success, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function setupTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS `clinic_users` (
            `id` BIGINT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `first_name` VARCHAR(100) DEFAULT '',
            `last_name` VARCHAR(100) DEFAULT '',
            `phone` VARCHAR(11) NOT NULL UNIQUE,
            `age` INT DEFAULT 0,
            `gender` VARCHAR(10) DEFAULT '',
            `height` INT DEFAULT 0,
            `weight` DECIMAL(6,1) DEFAULT 0,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` VARCHAR(20) DEFAULT 'user',
            `verified` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_phone` (`phone`),
            INDEX `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_appointments` (
            `id` BIGINT PRIMARY KEY,
            `patient_phone` VARCHAR(11) NOT NULL,
            `patient_name` VARCHAR(200) DEFAULT '',
            `date` VARCHAR(20) NOT NULL,
            `time` VARCHAR(20) NOT NULL,
            `type` VARCHAR(100) DEFAULT '',
            `notes` TEXT,
            `status` VARCHAR(20) DEFAULT 'pending',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_patient_phone` (`patient_phone`),
            INDEX `idx_date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_mealplans` (
            `id` BIGINT PRIMARY KEY,
            `patient_phone` VARCHAR(11) NOT NULL,
            `patient_name` VARCHAR(200) DEFAULT '',
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `breakfast` TEXT,
            `snack1` TEXT,
            `lunch` TEXT,
            `snack2` TEXT,
            `dinner` TEXT,
            `calories` INT DEFAULT 0,
            `duration` INT DEFAULT 7,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at_time` BIGINT DEFAULT 0,
            INDEX `idx_patient_phone` (`patient_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_explans` (
            `id` BIGINT PRIMARY KEY,
            `patient_phone` VARCHAR(11) NOT NULL,
            `patient_name` VARCHAR(200) DEFAULT '',
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `sat` TEXT,
            `sun` TEXT,
            `mon` TEXT,
            `tue` TEXT,
            `wed` TEXT,
            `thu` TEXT,
            `calories` INT DEFAULT 0,
            `weeks` INT DEFAULT 4,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at_time` BIGINT DEFAULT 0,
            INDEX `idx_patient_phone` (`patient_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_messages` (
            `id` BIGINT PRIMARY KEY,
            `from_phone` VARCHAR(11) NOT NULL,
            `to_phone` VARCHAR(11) NOT NULL,
            `text` TEXT NOT NULL,
            `date` VARCHAR(50) DEFAULT '',
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at_time` BIGINT DEFAULT 0,
            INDEX `idx_from` (`from_phone`),
            INDEX `idx_to` (`to_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_tests` (
            `id` BIGINT PRIMARY KEY,
            `patient_phone` VARCHAR(11) NOT NULL,
            `title` VARCHAR(200) DEFAULT '',
            `description` TEXT,
            `image` MEDIUMBLOB DEFAULT NULL,
            `file_url` VARCHAR(500) DEFAULT '',
            `status` VARCHAR(20) DEFAULT 'pending',
            `doctor_note` TEXT,
            `date` VARCHAR(50) DEFAULT '',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at_time` BIGINT DEFAULT 0,
            INDEX `idx_patient_phone` (`patient_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_progress` (
            `id` BIGINT PRIMARY KEY,
            `patient_phone` VARCHAR(11) NOT NULL,
            `weight` DECIMAL(6,1) NOT NULL,
            `waist` DECIMAL(6,1) DEFAULT NULL,
            `note` TEXT,
            `date` VARCHAR(50) DEFAULT '',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at_time` BIGINT DEFAULT 0,
            INDEX `idx_patient_phone` (`patient_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_water` (
            `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
            `patient_phone` VARCHAR(11) NOT NULL,
            `date` VARCHAR(20) NOT NULL,
            `count` INT DEFAULT 0,
            `glasses` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_phone_date` (`patient_phone`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `clinic_notifications` (
            `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
            `phone` VARCHAR(11) NOT NULL,
            `nkey` VARCHAR(100) NOT NULL,
            `value` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_phone_key` (`phone`, `nkey`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) {}
    }

    // Migrate legacy installs: clinic_messages.id was INT AUTO_INCREMENT,
    // which forced duplicate inserts on every sync. Convert to BIGINT
    // so client-generated Date.now() ids dedupe correctly.
    try { $pdo->exec("ALTER TABLE `clinic_messages` MODIFY COLUMN `id` BIGINT NOT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `clinic_messages` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)"); } catch (Exception $e) {}
    // Migrate legacy installs: message read-state was client-only.
    try { $pdo->exec("ALTER TABLE `clinic_messages` ADD COLUMN `is_read` TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}

    // Seed admin accounts if not exist
    seedAdminAccounts($pdo);
}

function seedAdminAccounts($pdo) {
    $accounts = [
        ['id' => 90000000000, 'name' => 'دکتر ظهرابی', 'first_name' => 'دکتر', 'last_name' => 'ظهرابی', 'phone' => '09000000000', 'gender' => 'male', 'role' => 'doctor', 'password_hash' => password_hash('zohrabi1366', PASSWORD_BCRYPT)],
        ['id' => 91111111111, 'name' => 'منشی کلینیک', 'first_name' => 'منشی', 'last_name' => 'کلینیک', 'phone' => '09111111111', 'gender' => 'female', 'role' => 'receptionist', 'password_hash' => password_hash('reception2024', PASSWORD_BCRYPT)],
    ];
    
    foreach ($accounts as $acc) {
        $stmt = $pdo->prepare("SELECT id FROM clinic_users WHERE phone = ?");
        $stmt->execute([$acc['phone']]);
        if (!$stmt->fetch()) {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO clinic_users (id, name, first_name, last_name, phone, age, gender, height, weight, password_hash, role, verified, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, 1, ?)");
            $stmt->execute([$acc['id'], $acc['name'], $acc['first_name'], $acc['last_name'], $acc['phone'], $acc['gender'], $acc['password_hash'], $acc['role'], $now]);
        }
    }
}

function authMe() {
    if (empty($_SESSION['clinic_user_id'])) {
        // Deliberately HTTP 200 (not 401): "nobody logged in" is a routine
        // answer polled on every page load, not an error. Clients key off
        // the explicit `loggedOut` flag instead of the status code.
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in',
            'data' => null,
            'loggedOut' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, first_name, last_name, phone, age, gender, height, weight, role, verified, created_at FROM clinic_users WHERE id = ?");
    $stmt->execute([$_SESSION['clinic_user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'User not found', null, 404);
    }

    // NOTE: clients read BOTH `data` and `user` (legacy mismatch) —
    // include the user under both keys so session restore always works.
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '',
        'data' => $user,
        'user' => $user
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function authLogin($input, $pdo) {
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        jsonResponse(false, 'Phone and password are required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM clinic_users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(false, 'Invalid phone or password');
    }
    
    $_SESSION['clinic_user_id'] = $user['id'];
    
    unset($user['password_hash']);
    jsonResponse(true, 'Login successful', $user);
}

function authRegister($input, $pdo) {
    $first = trim($input['first'] ?? '');
    $last = trim($input['last'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $age = intval($input['age'] ?? 0);
    $gender = $input['gender'] ?? 'female';
    $height = intval($input['height'] ?? 0);
    $weight = floatval($input['weight'] ?? 0);
    $password = $input['password'] ?? '';
    
    if (empty($first) || empty($last) || empty($phone) || empty($password)) {
        jsonResponse(false, 'All fields are required');
    }
    
    if (strlen($phone) !== 11 || substr($phone, 0, 2) !== '09') {
        jsonResponse(false, 'Invalid phone number');
    }
    
    if (strlen($password) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters');
    }
    
    $stmt = $pdo->prepare("SELECT id FROM clinic_users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'This phone number is already registered');
    }
    
    $id = intval($phone);
    $name = $first . ' ' . $last;
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO clinic_users (id, name, first_name, last_name, phone, age, gender, height, weight, password_hash, role, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 1, ?)");
    $stmt->execute([$id, $name, $first, $last, $phone, $age, $gender, $height, $weight, $hash, $now]);
    
    $_SESSION['clinic_user_id'] = $id;
    
    jsonResponse(true, 'Registration successful', [
        'id' => $id,
        'name' => $name,
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'age' => $age,
        'gender' => $gender,
        'height' => $height,
        'weight' => $weight,
        'role' => 'user',
        'verified' => true
    ]);
}

function authLogout() {
    session_destroy();
    jsonResponse(true, 'Logged out');
}

/** Convert a JS item's keys to DB column names */
function jsToDb(array $item, array $map, array $strip): array {
    $out = [];
    foreach ($item as $k => $v) {
        if (in_array($k, $strip, true)) continue;          // drop JS-only keys
        $out[$map[$k] ?? $k] = $v;                          // remap or keep as-is
    }
    return $out;
}

/** Convert a DB row's keys back to JS camelCase */
function dbToJs(array $row, array $map): array {
    $out = [];
    foreach ($row as $k => $v) {
        $out[$map[$k] ?? $k] = $v;
    }
    return $out;
}

function handleCrud($table, $method, $input, $pdo, $phoneFilter, $retried = false) {
    global $JS_TO_DB_MAP, $DB_TO_JS_MAP, $JS_ONLY_KEYS, $AUTO_INCREMENT_TABLES;

    try {
    switch ($method) {
        case 'GET':
            $phone = $_GET['phone'] ?? '';
            
            if ($phoneFilter && !empty($phone)) {
                $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE patient_phone = ? ORDER BY id DESC");
                $stmt->execute([$phone]);
            } elseif ($table === 'clinic_users') {
                $stmt = $pdo->query("SELECT id, name, first_name, last_name, phone, age, gender, height, weight, role, verified, created_at FROM {$table} ORDER BY id DESC");
            } else {
                $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY id DESC");
            }
            
            $rows = $stmt->fetchAll();

            // Map DB snake_case columns back to JS camelCase
            if ($table !== 'clinic_users') {
                $rows = array_map(function($row) use ($DB_TO_JS_MAP, $table) {
                    $row = dbToJs($row, $DB_TO_JS_MAP);
                    // TINYINT comes back as int/string depending on driver —
                    // normalize so JS `!m.read` works on every device.
                    if ($table === 'clinic_messages' && array_key_exists('read', $row)) {
                        $row['read'] = !empty($row['read']);
                    }
                    return $row;
                }, $rows);
            }

            jsonResponse(true, '', $rows);
            break;
            
        case 'POST':
            $data = $input['data'] ?? [];
            $synced = 0;
            
            if ($table === 'clinic_users') {
                // Upsert users — never delete existing ones (preserves other devices' data)
                foreach ($data as $item) {
                    if (isset($item['phone']) && !empty($item['phone'])) {
                        $id = intval($item['phone']);
                        $existing = null;
                        $check = $pdo->prepare("SELECT password_hash FROM clinic_users WHERE phone = ?");
                        $check->execute([$item['phone']]);
                        $existing = $check->fetch();
                        
                        $hash = $existing ? $existing['password_hash'] : password_hash($item['password'] ?? 'default123', PASSWORD_BCRYPT);
                        if (!empty($item['password']) && $item['password'] !== 'default123') {
                            $hash = password_hash($item['password'], PASSWORD_BCRYPT);
                        }
                        
                        $stmt = $pdo->prepare("INSERT INTO clinic_users (id, name, first_name, last_name, phone, age, gender, height, weight, password_hash, role, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), first_name=VALUES(first_name), last_name=VALUES(last_name), age=VALUES(age), gender=VALUES(gender), height=VALUES(height), weight=VALUES(weight), role=VALUES(role), verified=VALUES(verified)");
                        $stmt->execute([
                            $id,
                            $item['name'] ?? '',
                            $item['first'] ?? $item['first_name'] ?? '',
                            $item['last'] ?? $item['last_name'] ?? '',
                            $item['phone'],
                            $item['age'] ?? 0,
                            $item['gender'] ?? '',
                            $item['height'] ?? 0,
                            $item['weight'] ?? 0,
                            $hash,
                            $item['role'] ?? 'user',
                            $item['verified'] ?? $item['email_verified'] ?? 1,
                            $item['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                        $synced++;
                    }
                }
                jsonResponse(true, 'Synced ' . $synced . ' users');
                break;
            }
            
            // For other tables, upsert each record (merge, never delete)
            $skipId = in_array($table, $AUTO_INCREMENT_TABLES ?? [], true);

            foreach ($data as $item) {
                // Map JS camelCase keys → DB snake_case columns
                $item = jsToDb($item, $JS_TO_DB_MAP, $JS_ONLY_KEYS);

                // For phone-filtered tables, inject patient_phone from request param
                if ($phoneFilter && empty($item['patient_phone'])) {
                    $item['patient_phone'] = $_GET['phone'] ?? $input['phone'] ?? '';
                }

                $columns = [];
                $values = [];
                $params = [];
                
                foreach ($item as $key => $value) {
                    if ($key === 'id' && ($skipId || empty($value))) continue;
                    if ($key === 'is_read') $value = !empty($value) ? 1 : 0;
                    $columns[] = $key;
                    $values[] = ':' . $key;
                    $params[$key] = $value;
                }
                
                if (!empty($columns)) {
                    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE ";
                    $updates = [];
                    foreach ($columns as $col) {
                        if ($col !== 'id') {
                            $updates[] = "{$col} = VALUES({$col})";
                        }
                    }
                    if (!empty($updates)) {
                        $sql .= implode(', ', $updates);
                    }
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $synced++;
                    } catch (Exception $e) {
                        error_log("[API] Upsert error on {$table}: " . $e->getMessage());
                    }
                }
            }
            
            jsonResponse(true, 'Synced ' . $synced . ' records');
            break;
            
        case 'DELETE':
            // DELETE: remove records by phone or id
            $deleted = 0;
            if ($table === 'clinic_users') {
                $phone = $input['phone'] ?? $_GET['phone'] ?? '';
                if (!empty($phone)) {
                    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $deleted = $stmt->rowCount();
                    // Also clean up related data
                    $relatedTables = [
                        'clinic_appointments' => 'patient_phone',
                        'clinic_mealplans' => 'patient_phone',
                        'clinic_explans' => 'patient_phone',
                        'clinic_messages' => null,
                        'clinic_tests' => 'patient_phone',
                        'clinic_progress' => 'patient_phone',
                        'clinic_water' => 'patient_phone',
                        'clinic_notifications' => 'phone',
                    ];
                    foreach ($relatedTables as $rTable => $rCol) {
                        if ($rCol) {
                            $stmt = $pdo->prepare("DELETE FROM {$rTable} WHERE {$rCol} = ?");
                            $stmt->execute([$phone]);
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM {$rTable} WHERE from_phone = ? OR to_phone = ?");
                            $stmt->execute([$phone, $phone]);
                        }
                    }
                }
            } else {
                $phone = $input['phone'] ?? $_GET['phone'] ?? '';
                if (!empty($phone)) {
                    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE patient_phone = ?");
                    $stmt->execute([$phone]);
                    $deleted = $stmt->rowCount();
                }
            }
            jsonResponse(true, 'Deleted ' . $deleted . ' records');
            break;

        default:
            jsonResponse(false, 'Method not allowed', null, 405);
    }
    } catch (Exception $e) {
        if (!$retried && isMissingTable($e)) {
            try { setupTables($pdo); } catch (Exception $ignored) {}
            handleCrud($table, $method, $input, $pdo, $phoneFilter, true);
            return;
        }
        error_log("[API] crud error on {$table}: " . $e->getMessage());
        jsonResponse(false, 'Server error', null, 500);
    }
}

/** Delete one row by id. Table is whitelisted — never pass user input here. */
function handleDeleteById($table, $input, $pdo) {
    $allowed = ['clinic_mealplans' => true, 'clinic_explans' => true];
    if (!isset($allowed[$table])) {
        jsonResponse(false, 'Not allowed', null, 403);
    }
    $id = intval($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'id required');
    }
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(true, 'Deleted ' . $stmt->rowCount() . ' records');
}

/** True when $e is a "table doesn't exist" DB error (healable). */
function isMissingTable($e) {
    $msg = ($e instanceof PDOException ? $e->getCode() . ' ' : '') . $e->getMessage();
    return stripos($msg, '42S02') !== false
        || stripos($msg, 'Base table') !== false
        || stripos($msg, "doesn't exist") !== false
        || stripos($msg, 'no such table') !== false;
}

function handleWater($method, $input, $pdo, $retried = false) {
    try {
    switch ($method) {
        case 'GET':
            $phone = $_GET['phone'] ?? '';
            $date = $_GET['date'] ?? '';
            if (!empty($phone) && !empty($date)) {
                $stmt = $pdo->prepare("SELECT * FROM clinic_water WHERE patient_phone = ? AND date = ?");
                $stmt->execute([$phone, $date]);
                $row = $stmt->fetch();
                jsonResponse(true, '', $row ? [
                    'count' => intval($row['count']),
                    'glasses' => json_decode($row['glasses'], true) ?: []
                ] : ['count' => 0, 'glasses' => []]);
            } elseif (!empty($phone)) {
                $stmt = $pdo->prepare("SELECT date, count, glasses FROM clinic_water WHERE patient_phone = ? ORDER BY date DESC");
                $stmt->execute([$phone]);
                $rows = $stmt->fetchAll();
                $result = [];
                foreach ($rows as $r) {
                    $result[$r['date']] = [
                        'count' => intval($r['count']),
                        'glasses' => json_decode($r['glasses'], true) ?: []
                    ];
                }
                jsonResponse(true, '', $result);
            } else {
                // Bulk fetch for doctor/receptionist (no phone): all patients,
                // grouped as { phone: { date: {count, glasses} } } so one
                // request syncs every device instead of N per-patient calls.
                $stmt = $pdo->query("SELECT patient_phone, date, count, glasses FROM clinic_water ORDER BY date DESC");
                $rows = $stmt->fetchAll();
                $result = [];
                foreach ($rows as $r) {
                    $ph = $r['patient_phone'] ?? '';
                    if ($ph === '') continue;
                    if (!isset($result[$ph])) $result[$ph] = [];
                    $result[$ph][$r['date']] = [
                        'count' => intval($r['count']),
                        'glasses' => json_decode($r['glasses'], true) ?: []
                    ];
                }
                jsonResponse(true, '', $result);
            }
            break;
        case 'POST':
            $phone = $input['phone'] ?? '';
            $date = $input['date'] ?? '';
            $count = intval($input['count'] ?? 0);
            $glasses = $input['glasses'] ?? [];
            if (empty($phone) || empty($date)) {
                jsonResponse(false, 'phone and date required');
            }
            $stmt = $pdo->prepare("INSERT INTO clinic_water (patient_phone, date, count, glasses) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE count=VALUES(count), glasses=VALUES(glasses)");
            $stmt->execute([$phone, $date, $count, json_encode($glasses)]);
            jsonResponse(true, 'Water synced');
            break;
        case 'DELETE':
            $phone = $input['phone'] ?? $_GET['phone'] ?? '';
            $date = $input['date'] ?? $_GET['date'] ?? '';
            if (empty($phone)) {
                jsonResponse(false, 'phone required');
            }
            if (!empty($date)) {
                $stmt = $pdo->prepare("DELETE FROM clinic_water WHERE patient_phone = ? AND date = ?");
                $stmt->execute([$phone, $date]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM clinic_water WHERE patient_phone = ?");
                $stmt->execute([$phone]);
            }
            jsonResponse(true, 'Deleted ' . $stmt->rowCount() . ' water records');
            break;
        default:
            jsonResponse(false, 'Method not allowed', null, 405);
    }
    } catch (Exception $e) {
        if (!$retried && isMissingTable($e)) {
            try { setupTables($pdo); } catch (Exception $ignored) {}
            handleWater($method, $input, $pdo, true);
            return;
        }
        error_log('[API] water error: ' . $e->getMessage());
        jsonResponse(false, 'Water error', null, 500);
    }
}

function handleNotifications($method, $input, $pdo, $retried = false) {
    try {
    switch ($method) {
        case 'GET':
            $phone = $_GET['phone'] ?? '';
            if (empty($phone)) {
                jsonResponse(false, 'phone required');
            }
            $stmt = $pdo->prepare("SELECT nkey, value FROM clinic_notifications WHERE phone = ?");
            $stmt->execute([$phone]);
            $rows = $stmt->fetchAll();
            $result = [];
            foreach ($rows as $r) {
                $result[$r['nkey']] = json_decode($r['value'], true);
            }
            jsonResponse(true, '', $result);
            break;
        case 'POST':
            $phone = $input['phone'] ?? '';
            $entries = $input['entries'] ?? [];
            if (empty($phone)) {
                jsonResponse(false, 'phone required');
            }
            $synced = 0;
            foreach ($entries as $nkey => $value) {
                $stmt = $pdo->prepare("INSERT INTO clinic_notifications (phone, nkey, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
                $stmt->execute([$phone, $nkey, is_string($value) ? $value : json_encode($value)]);
                $synced++;
            }
            jsonResponse(true, 'Synced ' . $synced . ' notification entries');
            break;
        default:
            jsonResponse(false, 'Method not allowed', null, 405);
    }
    } catch (Exception $e) {
        if (!$retried && isMissingTable($e)) {
            try { setupTables($pdo); } catch (Exception $ignored) {}
            handleNotifications($method, $input, $pdo, true);
            return;
        }
        error_log('[API] notifications error: ' . $e->getMessage());
        jsonResponse(false, 'Notifications error', null, 500);
    }
}

function handleDebug($pdo) {
    $result = ['php_version' => phpversion()];
    
    try {
        $result['db_connection'] = 'OK';
        $result['db_name'] = DB_NAME;
        
        $tables = ['clinic_users', 'clinic_appointments', 'clinic_mealplans', 'clinic_explans', 'clinic_messages', 'clinic_tests', 'clinic_progress', 'clinic_water', 'clinic_notifications'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
                $result[$table] = $stmt->fetch()['cnt'];
            } catch (Exception $e) {
                $result[$table] = 'error: ' . $e->getMessage();
            }
        }
        
        $result['session'] = $_SESSION;
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    jsonResponse(true, '', $result);
}
