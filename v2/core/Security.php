<?php
/**
 * Security Class
 * 
 * Handles CSRF protection, XSS prevention, and input sanitization.
 * Enterprise-grade security for all user inputs and outputs.
 * 
 * @package Core
 */

class Security
{
    private const CSRF_TOKEN_NAME = 'csrf_token';
    private const CSRF_TOKEN_LENGTH = 64;
    private const CSRF_TOKEN_EXPIRY = 3600; // 1 hour

    /**
     * Generate CSRF token and store in session
     * 
     * @return string The generated token
     */
    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH / 2));
        
        // Store token with expiry time
        $_SESSION[self::CSRF_TOKEN_NAME] = [
            'token' => $token,
            'expires' => time() + self::CSRF_TOKEN_EXPIRY
        ];
        
        return $token;
    }

    /**
     * Get current CSRF token (generate if not exists)
     * 
     * @return string CSRF token
     */
    public static function csrfToken(): string
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME]) || 
            time() > $_SESSION[self::CSRF_TOKEN_NAME]['expires']) {
            self::generateCsrfToken();
        }
        
        return $_SESSION[self::CSRF_TOKEN_NAME]['token'];
    }

    /**
     * Output hidden input field for CSRF token in forms
     * 
     * @return string HTML hidden input field
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="' . self::CSRF_TOKEN_NAME . '" value="' . $token . '">';
    }

    /**
     * Verify CSRF token from form submission
     * 
     * @return bool True if token is valid
     */
    public static function verifyCsrfToken(): bool
    {
        $token = $_POST[self::CSRF_TOKEN_NAME] ?? 
                 $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($token)) {
            Logger::error('CSRF token missing from request');
            return false;
        }

        // Check if token exists and hasn't expired
        if (!isset($_SESSION[self::CSRF_TOKEN_NAME])) {
            Logger::error('No CSRF token in session');
            return false;
        }

        if (time() > $_SESSION[self::CSRF_TOKEN_NAME]['expires']) {
            Logger::error('CSRF token expired');
            return false;
        }

        // Timing-safe comparison to prevent timing attacks
        $valid = hash_equals($_SESSION[self::CSRF_TOKEN_NAME]['token'], $token);

        if (!$valid) {
            Logger::error('CSRF token mismatch');
        }

        // Regenerate token after validation
        self::generateCsrfToken();

        return $valid;
    }

    /**
     * Middleware: Check CSRF token for POST/PUT/DELETE requests
     * 
     * @return bool True if validation passes
     */
    public static function csrfMiddleware(): bool
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            if (!self::verifyCsrfToken()) {
                Session::flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '/');
                exit;
            }
        }
        return true;
    }

    /**
     * Escape output data (Anti-XSS)
     * 
     * @param string $data Data to escape
     * @return string Escaped data safe for HTML output
     */
    public static function escape(string $data): string
    {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize input string
     * 
     * Trims whitespace and removes null bytes.
     * HTML escaping is done on OUTPUT via escape() / e(), not on input.
     * 
     * @param string $input Input to sanitize
     * @return string Sanitized input
     */
    public static function sanitize(string $input): string
    {
        // Trim whitespace
        $input = trim($input);
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        return $input;
    }

    /**
     * Sanitize email address
     * 
     * @param string $email Email to sanitize
     * @return string Sanitized email
     */
    public static function sanitizeEmail(string $email): string
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return $email ?: '';
    }

    /**
     * Sanitize numeric input
     * 
     * @param mixed $input Input to sanitize
     * @return int Sanitized integer
     */
    public static function sanitizeInt($input): int
    {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float input
     * 
     * @param mixed $input Input to sanitize
     * @return float Sanitized float
     */
    public static function sanitizeFloat($input): float
    {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @return array Array of validation errors
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return $errors;
    }

    /**
     * Check if the request is AJAX
     * 
     * @return bool True if request is AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim(end($ips));
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Generate random string
     * 
     * @param int $length Length of the string
     * @return string Random string
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Encrypt data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public static function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $key = hash('sha256', SECURITY_KEY, true);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     * 
     * @param string $data Data to decrypt
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt(string $data)
    {
        $key = hash('sha256', SECURITY_KEY, true);
        $decoded = base64_decode($data);
        
        if ($decoded === false || strlen($decoded) < 17) {
            return false;
        }
        
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
