<?php
/**
 * Autoloader
 * 
 * Automatically loads class files on demand.
 * Uses PSR-4 inspired naming convention.
 * 
 * @package Core
 */

// Register autoloader
spl_autoload_register(function ($class) {
    // Define base directories for classes
    $baseDir = ROOT_PATH;
    
    // Map namespace prefixes to base directories
    $prefixes = [
        'App\\Controllers\\' => $baseDir . '/app/controllers/',
        'App\\Controllers\\Admin\\' => $baseDir . '/app/controllers/admin/',
        'App\\Models\\' => $baseDir . '/app/models/',
    ];

    // Check each prefix
    foreach ($prefixes as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get relative class name
        $relativeClass = substr($class, $len);

        // Convert namespace separators to directory separators
        $file = $dir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Fallback: Try direct file loading for non-namespaced classes
    $file = $baseDir . '/core/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }
});

/**
 * Simple function to load core files if needed
 */
function loadCoreClasses(): void
{
    $coreClasses = [
        'Database',
        'Router',
        'Controller',
        'Model',
        'Security',
        'Auth',
        'Cache',
        'Logger',
        'Validation',
        'FileHandler',
        'ErrorHandler'
    ];

    foreach ($coreClasses as $class) {
        $file = ROOT_PATH . '/core/' . $class . '.php';
        if (file_exists($file) && !class_exists($class)) {
            require_once $file;
        }
    }
}
