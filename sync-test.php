<?php
/**
 * Sync Test Script — Upload to server root, visit in browser, then DELETE
 */
header('Content-Type: text/plain; charset=utf-8');
echo "=== CLINIC SYNC VERIFICATION ===\n\n";

// 1. Check OPcache
echo "1. OPcache:\n";
if (function_exists('opcache_get_status')) {
    $status = @opcache_get_status(false);
    if ($status && $status['opcache_enabled']) {
        echo "   Enabled (scripts cached: " . ($status['opcache_statistics']['num_cached_scripts'] ?? '?') . ")\n";
        echo "   Clearing... ";
        if (opcache_reset()) {
            echo "CLEARED ✓\n";
        } else {
            echo "FAILED (run from CLI: php -r 'opcache_reset();')\n";
        }
    } else {
        echo "   Not active ✓\n";
    }
} else {
    echo "   Not available\n";
}

// 2. Check config loading
echo "\n2. Config:\n";
define('ROOT_PATH', __DIR__);
if (file_exists('v2/config/config.php')) {
    require_once 'v2/config/config.php';
    echo "   DB_USER: " . DB_USER . "\n";
    echo "   DB_NAME: " . DB_NAME . "\n";
} else {
    echo "   ERROR: v2/config/config.php not found!\n";
    exit;
}

// 3. DB connection
echo "\n3. Database:\n";
try {
    $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4;port=%s", DB_HOST, DB_NAME, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "   Connection: OK ✓\n";
} catch (PDOException $e) {
    echo "   FAILED: " . $e->getMessage() . "\n";
    exit;
}

// 4. Check table columns match expected JS keys
echo "\n4. Column mapping check:\n";
$tables = [
    'clinic_appointments' => ['patient_phone', 'patient_name', 'date', 'time', 'type', 'notes', 'status', 'created_at'],
    'clinic_mealplans' => ['patient_phone', 'patient_name', 'title', 'description', 'breakfast', 'lunch', 'dinner', 'created_at', 'created_at_time'],
    'clinic_explans' => ['patient_phone', 'patient_name', 'title', 'description', 'sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'created_at', 'created_at_time'],
    'clinic_messages' => ['from_phone', 'to_phone', 'text', 'date', 'created_at', 'created_at_time'],
    'clinic_tests' => ['patient_phone', 'title', 'description', 'status', 'doctor_note', 'date', 'created_at', 'created_at_time'],
    'clinic_progress' => ['patient_phone', 'weight', 'waist', 'note', 'date', 'created_at', 'created_at_time'],
];
foreach ($tables as $table => $expectedCols) {
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $actualCols = array_column($stmt->fetchAll(), 'Field');
        $missing = array_diff($expectedCols, $actualCols);
        if (empty($missing)) {
            echo "   {$table}: OK ✓ (columns match)\n";
        } else {
            echo "   {$table}: MISSING COLUMNS: " . implode(', ', $missing) . "\n";
        }
    } catch (Exception $e) {
        echo "   {$table}: ERROR - " . $e->getMessage() . "\n";
    }
}

// 5. Check API is reachable and column mapping works
echo "\n5. API column mapping test:\n";
try {
    // Test INSERT with JS camelCase keys (simulating what api-sync.js sends)
    $testPhone = '00000000000';
    $testData = [
        'id' => time(),
        'patientPhone' => $testPhone,
        'patientName' => 'Test Patient',
        'date' => '2026-01-01',
        'time' => '10:00',
        'type' => 'test',
        'notes' => 'sync test',
        'status' => 'pending',
        'createdAt' => date('Y-m-d H:i:s'),
    ];
    
    // Convert using same logic as api/index.php
    $JS_TO_DB_MAP = [
        'patientPhone' => 'patient_phone',
        'patientName' => 'patient_name',
        'createdAt' => 'created_at',
        'createdAtTime' => 'created_at_time',
        'from' => 'from_phone',
        'to' => 'to_phone',
        'desc' => 'description',
        'doctorNote' => 'doctor_note',
    ];
    
    $dbItem = [];
    foreach ($testData as $k => $v) {
        $dbItem[$JS_TO_DB_MAP[$k] ?? $k] = $v;
    }
    
    // Insert
    $columns = array_keys($dbItem);
    $values = array_map(function($c) { return ":{$c}"; }, $columns);
    $sql = "INSERT INTO clinic_appointments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE status=VALUES(status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dbItem);
    echo "   INSERT with camelCase mapping: OK ✓\n";
    
    // Verify it can be read back
    $stmt = $pdo->prepare("SELECT * FROM clinic_appointments WHERE patient_phone = ?");
    $stmt->execute([$testPhone]);
    $row = $stmt->fetch();
    if ($row) {
        // Convert back to JS camelCase
        $dbToJs = array_flip($JS_TO_DB_MAP);
        $jsRow = [];
        foreach ($row as $k => $v) {
            $jsRow[$dbToJs[$k] ?? $k] = $v;
        }
        echo "   SELECT with reverse mapping: OK ✓\n";
        echo "   patientPhone: " . ($jsRow['patientPhone'] ?? 'MISSING') . "\n";
        echo "   createdAt: " . ($jsRow['createdAt'] ?? 'MISSING') . "\n";
    } else {
        echo "   SELECT: FAILED - row not found\n";
    }
    
    // Cleanup
    $stmt = $pdo->prepare("DELETE FROM clinic_appointments WHERE patient_phone = ?");
    $stmt->execute([$testPhone]);
    echo "   Cleanup: OK ✓\n";
    
} catch (Exception $e) {
    echo "   FAILED: " . $e->getMessage() . "\n";
}

// 6. Table row counts
echo "\n6. Current data:\n";
foreach (['clinic_users', 'clinic_appointments', 'clinic_mealplans', 'clinic_explans', 'clinic_messages', 'clinic_tests', 'clinic_progress'] as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "   {$table}: {$count} rows\n";
    } catch (Exception $e) {
        echo "   {$table}: ERROR\n";
    }
}

// 7. Check api/index.php has the fix
echo "\n7. api/index.php verification:\n";
$apiCode = file_get_contents('api/index.php');
$checks = [
    'JS_TO_DB_MAP' => 'Column mapping constant',
    'jsToDb' => 'jsToDb function',
    'dbToJs' => 'dbToJs function',
    'Cache-Control: no-store' => 'No-cache headers',
    "case 'DELETE'" => 'DELETE endpoint',
];
foreach ($checks as $search => $label) {
    if (strpos($apiCode, $search) !== false) {
        echo "   {$label}: FOUND ✓\n";
    } else {
        echo "   {$label}: MISSING ✗ — upload the updated api/index.php!\n";
    }
}

echo "\n=== DONE ===\n";
echo "DELETE this file after testing!\n";
