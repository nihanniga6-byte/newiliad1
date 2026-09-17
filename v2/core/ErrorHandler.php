<?php
/**
 * Error Handler
 * 
 * Catches ALL errors including:
 * - PHP errors (E_ALL)
 * - Exceptions
 * - Fatal errors
 * - Parse errors
 * 
 * Logs to file and displays user-friendly messages in production.
 * 
 * @package Core
 */

class ErrorHandler
{
    /**
     * Initialize error handling
     */
    public static function init(): void
    {
        // Set exception handler (catches Error, Exception, and ErrorException)
        set_exception_handler([self::class, 'handleException']);

        // Set shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        // Convert PHP errors to exceptions so they're caught by the exception handler
        set_error_handler(function($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Handle exceptions
     * 
     * @param Throwable $exception Exception object
     */
    public static function handleException(Throwable $exception): void
    {
        $error = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ];

        self::logError($error);
        self::showError($error);
    }

    /**
     * Handle PHP errors
     * 
     * @param int $severity Error severity
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number
     */
    public static function handleError(int $severity, string $message, string $file, int $line): void
    {
        $error = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'code' => $severity
        ];

        self::logError($error);
        self::showError($error);
    }

    /**
     * Handle shutdown (for fatal errors)
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'code' => $error['type']
            ];

            self::logError($errorData);
            
            // Only show error if no output has been sent
            if (ob_get_length() === 0) {
                self::showError($errorData);
            }
        }
    }

    /**
     * Log error to file
     * 
     * @param array $error Error data
     */
    private static function logError(array $error): void
    {
        if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
            Logger::error($error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'code' => $error['code']
            ]);
        }
    }

    /**
     * Show error to user
     * 
     * @param array $error Error data
     */
    private static function showError(array $error): void
    {
        // Don't send headers if already sent
        if (headers_sent()) {
            return;
        }

        http_response_code(500);

        if (defined('APP_ENV') && APP_ENV === 'development') {
            // Development: Show detailed error
            echo '<!DOCTYPE html><html><head><title>Error</title>';
            echo '<style>body{font-family:monospace;padding:40px;background:#1e293b;color:#e2e8f0;}';
            echo 'h1{color:#f87171;}h2{color:#fbbf24;}pre{background:#0f172a;padding:20px;border-radius:8px;overflow-x:auto;}';
            echo '.label{color:#94a3b8;}</style></head><body>';
            echo '<h1>Error</h1>';
            echo '<h2>' . htmlspecialchars($error['message']) . '</h2>';
            echo '<p><span class="label">File:</span> ' . htmlspecialchars($error['file']) . '</p>';
            echo '<p><span class="label">Line:</span> ' . $error['line'] . '</p>';
            echo '<p><span class="label">Code:</span> ' . $error['code'] . '</p>';
            echo '</body></html>';
        } else {
            // Production: Show user-friendly message
            echo '<!DOCTYPE html><html><head><title>Error</title>';
            echo '<style>body{font-family:-apple-system,sans-serif;padding:40px;text-align:center;background:#f8fafc;color:#334155;}';
            echo 'h1{color:#dc2626;font-size:3rem;margin-bottom:1rem;}';
            echo 'p{color:#64748b;font-size:1.1rem;margin-bottom:2rem;}';
            echo 'a{color:#16a34a;text-decoration:none;font-weight:600;}</style></head><body>';
            echo '<h1>Oops!</h1>';
            echo '<p>Something went wrong. Our team has been notified.</p>';
            echo '<p>Please try again later or <a href="/">return to homepage</a>.</p>';
            echo '</body></html>';
        }
        
        exit;
    }
}
