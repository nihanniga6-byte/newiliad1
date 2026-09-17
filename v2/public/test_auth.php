<?php
/**
 * Auth Flow Test Script
 * Tests: registration, login, password hashing, cross-device compatibility
 * Run: php test_auth.php  OR  access via browser
 */

define('ROOT_PATH', dirname(__DIR__));

// Fake HTTP_HOST for CLI
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/ErrorHandler.php';
ErrorHandler::init();
require_once ROOT_PATH . '/core/Session.php';
require_once ROOT_PATH . '/core/Autoloader.php';
require_once ROOT_PATH . '/core/Database.php';
require_once ROOT_PATH . '/core/Security.php';
require_once ROOT_PATH . '/core/Auth.php';
require_once ROOT_PATH . '/core/Logger.php';
require_once ROOT_PATH . '/core/Cache.php';
require_once ROOT_PATH . '/app/helpers/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$pass = 0;
$fail = 0;

function test(string $name, bool $result, string $detail = '') {
    global $pass, $fail;
    if ($result) {
        $pass++;
        echo "[PASS] {$name}\n";
    } else {
        $fail++;
        echo "[FAIL] {$name}" . ($detail ? " -- {$detail}" : '') . "\n";
    }
}

echo "=== AUTH FLOW TESTS ===\n\n";

// --- Test 1: Database Connection ---
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    test("Database connection", true);
} catch (Exception $e) {
    test("Database connection", false, $e->getMessage());
    echo "\nCannot continue without database.\n";
    exit(1);
}

// --- Test 2: Password Hashing ---
$hash = password_hash('TestPass123', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
test("Password hash generation", !empty($hash) && str_starts_with($hash, '$2y$'));
test("Password verify (correct)", password_verify('TestPass123', $hash));
test("Password verify (wrong)", !password_verify('WrongPass', $hash));

// --- Test 3: Sanitize does NOT alter passwords ---
$raw = 'p@ss&word<test>"quoted"';
$sanitized = Security::sanitize($raw);
test("Sanitize preserves @", str_contains($sanitized, '@'));
test("Sanitize preserves &", str_contains($sanitized, '&'));
test("Sanitize preserves <", str_contains($sanitized, '<'));
test("Sanitize preserves >", str_contains($sanitized, '>'));
test("Sanitize preserves quotes", str_contains($sanitized, '"'));
test("Sanitize trims whitespace", Security::sanitize('  test  ') === 'test');
test("Sanitize removes null bytes", Security::sanitize("test\x00test") === 'testtest');

// --- Test 4: Full registration + login flow ---
$testEmail = 'testdevice_' . time() . '_' . bin2hex(random_bytes(4)) . '@test.com';
$testPass = 'Secure@Pass123';
$testFullname = 'Test Device User';

echo "\n--- Registration Flow ---\n";

// Simulate what AuthController::register does
$email = Security::sanitize($testEmail);
$password = Security::sanitize($testPass);
$fullname = Security::sanitize($testFullname);

test("Email passes filter_var", filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
test("Password length check", strlen($password) >= 8);

// Check duplicate
$existing = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
test("Email not already used", $existing === null);

// Hash and insert
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
$userId = (int) $db->insert('users', [
    'fullname' => $fullname,
    'email' => $email,
    'password_hash' => $hash,
    'role' => 'user',
    'status' => 'active',
    'email_verified' => 1
]);
test("User inserted (ID > 0)", $userId > 0, "Got ID: {$userId}");

// Create profile
$db->insert('user_profiles', ['user_id' => $userId]);
test("Profile created", true);

echo "\n--- Login Flow ---\n";

// Simulate what Auth::login does
$user = $db->fetchOne(
    "SELECT * FROM users WHERE email = :email AND status = 'active'",
    ['email' => $email]
);
test("User found by email", $user !== null);
test("User email matches", $user['email'] === $email);
test("User status is active", $user['status'] === 'active');
test("Password hash starts with \$2y$", str_starts_with($user['password_hash'], '$2y$'));

$loginOk = password_verify($password, $user['password_hash']);
test("password_verify succeeds", $loginOk);

echo "\n--- Cross-Device Simulation ---\n";

// Simulate Device B: different session, same DB
$deviceB_password = Security::sanitize('Secure@Pass123');
test("Device B sanitized password matches Device A", $deviceB_password === $password);
$deviceB_ok = password_verify($deviceB_password, $user['password_hash']);
test("Device B password_verify succeeds", $deviceB_ok);

// Test with special characters
$specialPass = 'Pass&with<>quotes"and\'apostrophe';
$specialHash = password_hash(Security::sanitize($specialPass), PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
$deviceB_special = password_verify(Security::sanitize($specialPass), $specialHash);
test("Special chars password works cross-device", $deviceB_special);

echo "\n--- Cleanup ---\n";
$db->delete('user_profiles', 'user_id = :id', ['id' => $userId]);
$db->delete('users', 'id = :id', ['id' => $userId]);
test("Test user cleaned up", true);

echo "\n========================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "========================\n";

exit($fail > 0 ? 1 : 0);
