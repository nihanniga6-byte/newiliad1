<?php
/**
 * Session Management Class
 * 
 * Handles secure session operations with strict cookie settings.
 * Implements session timeout and regeneration for security.
 * 
 * @package Core
 */

class Session
{
    /**
     * Initialize secure session
     * 
     * Sets secure cookie parameters and starts the session.
     * Must be called before any output is sent to browser.
     */
    public static function init(): void
    {
        // Don't reinitialize if already active
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set secure cookie parameters BEFORE starting session
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie (until browser closes)
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,  // HTTPS only when SSL is available
            'httponly' => true,  // No JavaScript access
            'samesite' => 'Lax' // Lax for compatibility with redirects
        ]);

        // Security settings
        ini_set('session.use_strict_mode', '1'); // Reject uninitialized sessions
        ini_set('session.use_only_cookies', '1'); // No session ID in URL
        ini_set('session.use_trans_sid', '0'); // Don't transmit session ID

        // Set session name
        session_name('APP_SESSID');

        // Start session
        session_start();

        // Check for session timeout
        self::checkTimeout();
    }

    /**
     * Check if session has timed out due to inactivity
     * 
     * If timeout occurs, destroy session and redirect to login.
     */
    private static function checkTimeout(): void
    {
        if (isset($_SESSION['last_activity'])) {
            $timePassed = time() - $_SESSION['last_activity'];
            
            if ($timePassed > SESSION_TIMEOUT) {
                // Session expired - destroy and redirect
                self::destroy();
                header('Location: /login?timeout=1');
                exit;
            }
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
    }

    /**
     * Regenerate session ID (call after login to prevent session fixation)
     * 
     * @param bool $deleteOld Delete the old session file
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    /**
     * Set a session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Session value or default
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists
     * 
     * @param string $key Session key
     * @return bool True if key exists
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]) && $_SESSION[$key] !== null;
    }

    /**
     * Remove a session value
     * 
     * @param string $key Session key to remove
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the entire session (logout)
     * 
     * Clears all session data and cookies.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear session data
            $_SESSION = [];

            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            // Destroy session
            session_destroy();
        }
    }

    /**
     * Set a flash message
     * 
     * Flash messages are displayed once and then removed.
     * 
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message text
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Get and clear a flash message
     * 
     * @param string $type Message type
     * @return string|null Message text or null
     */
    public static function getFlash(string $type): ?string
    {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }

    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in
     */
    public static function isLoggedIn(): bool
    {
        return self::has('user_id');
    }

    /**
     * Check if user is admin
     * 
     * @return bool True if admin
     */
    public static function isAdmin(): bool
    {
        return self::get('user_role') === 'admin';
    }

    /**
     * Get current user ID
     * 
     * @return int|null User ID or null
     */
    public static function userId(): ?int
    {
        $id = self::get('user_id');
        return $id ? (int) $id : null;
    }

    /**
     * Set remember me cookie
     * 
     * @param int $userId User ID
     * @param string $token Remember me token
     */
    public static function setRememberMe(int $userId, string $token): void
    {
        $expiry = time() + (REMEMBER_ME_DAYS * 24 * 60 * 60);
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(
            'remember_me',
            $userId . ':' . $token,
            $expiry,
            '/',
            '',
            $isSecure,
            true   // HttpOnly
        );
    }

    /**
     * Clear remember me cookie
     */
    public static function clearRememberMe(): void
    {
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_me', '', time() - 3600, '/', '', $isSecure, true);
    }

    /**
     * Get remember me cookie data
     * 
     * @return array|null Array with user_id and token, or null
     */
    public static function getRememberMe(): ?array
    {
        if (!isset($_COOKIE['remember_me'])) {
            return null;
        }

        $parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($parts) !== 2) {
            return null;
        }

        return [
            'user_id' => (int) $parts[0],
            'token' => $parts[1]
        ];
    }
}
