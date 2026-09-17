<?php
/**
 * Logger Class
 * 
 * Logs errors, warnings, and info messages to file.
 * Implements automatic log rotation when file exceeds size limit.
 * Critical for shared hosting where SSH access is not available.
 * 
 * @package Core
 */

class Logger
{
    private static string $logFile = '';

    /**
     * Initialize logger
     */
    private static function init(): void
    {
        if (empty(self::$logFile)) {
            self::$logFile = LOG_PATH . '/error.log';
            
            if (!is_dir(LOG_PATH)) {
                mkdir(LOG_PATH, 0755, true);
            }
        }
    }

    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param array $context Additional context data
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Log warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context data
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context data
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Log debug message (only in development)
     * 
     * @param string $message Debug message
     * @param array $context Additional context data
     */
    public static function debug(string $message, array $context = []): void
    {
        if (APP_ENV === 'development') {
            self::write('DEBUG', $message, $context);
        }
    }

    /**
     * Write log entry to file
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        self::init();

        // Check if log rotation is needed
        self::rotateIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';

        $logEntry = "[{$timestamp}] [{$level}] [{$ip}] [{$method} {$uri}] {$message}";

        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logEntry .= " | Context: {$contextStr}";
        }

        // Add stack trace for errors
        if ($level === 'ERROR') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $traceStr = '';
            foreach (array_slice($trace, 1, 3) as $i => $frame) {
                $file = $frame['file'] ?? 'unknown';
                $line = $frame['line'] ?? 0;
                $function = $frame['function'] ?? 'unknown';
                $traceStr .= " -> {$function}({$file}:{$line})";
            }
            if ($traceStr) {
                $logEntry .= " | Trace:{$traceStr}";
            }
        }

        $logEntry .= PHP_EOL;

        // Write to log file
        @file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private static function rotateIfNeeded(): void
    {
        if (!file_exists(self::$logFile)) {
            return;
        }

        $size = filesize(self::$logFile);
        if ($size < LOG_MAX_SIZE) {
            return;
        }

        // Archive old log
        $archiveName = LOG_PATH . '/error_' . date('Y-m-d_His') . '.log';
        rename(self::$logFile, $archiveName);

        // Create new log file
        file_put_contents(self::$logFile, '');
    }

    /**
     * Clear log file
     */
    public static function clear(): void
    {
        self::init();
        if (file_exists(self::$logFile)) {
            file_put_contents(self::$logFile, '');
        }
    }

    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to retrieve
     * @return string Recent log entries
     */
    public static function getRecent(int $lines = 100): string
    {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return '';
        }

        $log = file_get_contents(self::$logFile);
        $logLines = explode(PHP_EOL, trim($log));
        $recent = array_slice($logLines, -$lines);
        return implode(PHP_EOL, $recent);
    }

    /**
     * Get log file size
     * 
     * @return string Human-readable file size
     */
    public static function getFileSize(): string
    {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return '0 B';
        }

        $bytes = filesize(self::$logFile);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
