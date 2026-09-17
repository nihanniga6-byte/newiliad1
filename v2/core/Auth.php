<?php
/**
 * Authentication Class
 * 
 * Handles user login, registration, logout, and session management.
 * Implements secure password hashing and remember me functionality.
 * 
 * @package Core
 */

class Auth
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Authenticate user with email and password
     * 
     * @param string $email User email
     * @param string $password User password
     * @param bool $remember Remember me flag
     * @return array|null User data or null on failure
     */
    public function login(string $email, string $password, bool $remember = false): ?array
    {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = :email AND status = 'active'",
            ['email' => $email]
        );

        if (!$user) {
            Logger::warning("Login failed: user not found for email: {$email}");
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            Logger::warning("Login failed: password mismatch for email: {$email}");
            return null;
        }

        // Regenerate session ID to prevent session fixation
        Session::regenerate();

        // Reset session timeout on successful login
        $_SESSION['last_activity'] = time();

        // Set session data
        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['fullname']);
        Session::set('user_email', $user['email']);
        Session::set('user_role', $user['role']);
        Session::set('logged_in', true);
        Session::set('login_time', time());

        // Update last login
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $user['id']]);

        // Handle remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 24 * 60 * 60));
            
            $this->db->update('users', [
                'remember_token' => $token,
                'remember_expiry' => $expiry
            ], 'id = :id', ['id' => $user['id']]);

            Session::setRememberMe($user['id'], $token);
        }

        // Log activity
        $this->logActivity($user['id'], 'login');

        Logger::info("User logged in: {$user['email']}");
        
        // Update user data (without sensitive fields)
        unset($user['password_hash'], $user['remember_token'], $user['remember_expiry']);
        return $user;
    }

    /**
     * Register new user
     * 
     * @param array $data User data (fullname, email, password, etc.)
     * @return int|null User ID or null on failure
     */
    public function register(array $data): ?int
    {
        // Check if email already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = :email",
            ['email' => $data['email']]
        );

        if ($existing) {
            return null; // Email already exists
        }

        // Hash password with bcrypt (cost factor 12)
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST
        ]);

        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Insert user
        $userId = $this->db->insert('users', [
            'fullname' => $data['fullname'],
            'email' => $data['email'],
            'password_hash' => $hashedPassword,
            'role' => 'user',
            'status' => 'active',
            'email_verified' => 0,
            'verification_token' => $verificationToken
        ]);

        // Create user profile
        $this->db->insert('user_profiles', [
            'user_id' => $userId,
            'bio' => '',
            'avatar' => '',
            'phone' => $data['phone'] ?? '',
            'address' => ''
        ]);

        // Log activity
        $this->logActivity($userId, 'register');

        Logger::info("New user registered: {$data['email']} (ID: {$userId})");
        
        return $userId;
    }

    /**
     * Logout user
     * 
     * Clears session, cookies, and remember me token.
     */
    public function logout(): void
    {
        $userId = Session::userId();
        
        if ($userId) {
            // Clear remember token in database
            $this->db->update('users', [
                'remember_token' => null,
                'remember_expiry' => null
            ], 'id = :id', ['id' => $userId]);

            // Log activity
            $this->logActivity($userId, 'logout');
        }

        // Clear cookies
        Session::clearRememberMe();
        
        // Destroy session
        Session::destroy();
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool True if logged in
     */
    public static function check(): bool
    {
        return Session::isLoggedIn();
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            // Try remember me
            if (self::tryRememberMe()) {
                return; // Successfully logged in via remember me
            }
            
            Session::flash('warning', 'Please log in to continue.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * Require admin role (redirect if not admin)
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();
        
        if (!Session::isAdmin()) {
            Session::flash('error', 'Access denied. Admin privileges required.');
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Try to authenticate via remember me cookie
     * 
     * @return bool True if successfully logged in
     */
    public static function tryRememberMe(): bool
    {
        $rememberData = Session::getRememberMe();
        if (!$rememberData) {
            return false;
        }

        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = :id AND remember_token = :token AND remember_expiry > NOW()",
            ['id' => $rememberData['user_id'], 'token' => $rememberData['token']]
        );

        if (!$user) {
            Session::clearRememberMe();
            return false;
        }

        // Re-login the user
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['fullname']);
        Session::set('user_email', $user['email']);
        Session::set('user_role', $user['role']);
        Session::set('logged_in', true);
        Session::set('login_time', time());

        Logger::info("User logged in via remember me: {$user['email']}");
        return true;
    }

    /**
     * Get current authenticated user
     * 
     * @return array|null User data or null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = :id",
            ['id' => Session::userId()]
        );
        
        if ($user) {
            unset($user['password_hash'], $user['remember_token'], $user['remember_expiry']);
        }
        
        return $user;
    }

    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool True on success
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->db->fetchOne(
            "SELECT password_hash FROM users WHERE id = :id",
            ['id' => $userId]
        );

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST
        ]);

        $this->db->update('users', [
            'password_hash' => $hashedPassword
        ], 'id = :id', ['id' => $userId]);

        Logger::info("Password changed for user ID: {$userId}");
        return true;
    }

    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $data Profile data
     * @return bool True on success
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $allowed = ['fullname', 'email'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) {
            return false;
        }

        $this->db->update('users', $updateData, 'id = :id', ['id' => $userId]);

        // Update session data
        if (isset($updateData['fullname'])) {
            Session::set('user_name', $updateData['fullname']);
        }
        if (isset($updateData['email'])) {
            Session::set('user_email', $updateData['email']);
        }

        return true;
    }

    /**
     * Update user profile details
     * 
     * @param int $userId User ID
     * @param array $data Profile data (bio, phone, address)
     * @return bool True on success
     */
    public function updateProfileDetails(int $userId, array $data): bool
    {
        // Check if profile exists
        $existing = $this->db->fetchOne(
            "SELECT user_id FROM user_profiles WHERE user_id = :user_id",
            ['user_id' => $userId]
        );

        if ($existing) {
            return $this->db->update('user_profiles', $data, 'user_id = :user_id', ['user_id' => $userId]) !== false;
        } else {
            $data['user_id'] = $userId;
            return $this->db->insert('user_profiles', $data) > 0;
        }
    }

    /**
     * Log user activity
     * 
     * @param int $userId User ID
     * @param string $action Action performed
     */
    private function logActivity(int $userId, string $action): void
    {
        try {
            $this->db->insert('activity_logs', [
                'user_id' => $userId,
                'action' => $action,
                'ip_address' => Security::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Don't let logging failure break the application
            Logger::error("Failed to log activity: " . $e->getMessage());
        }
    }

    /**
     * Get user profile
     * 
     * @param int $userId User ID
     * @return array|null Profile data
     */
    public function getProfile(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM user_profiles WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }

    /**
     * Verify email address
     * 
     * @param string $token Verification token
     * @return bool True on success
     */
    public function verifyEmail(string $token): bool
    {
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE verification_token = :token AND email_verified = 0",
            ['token' => $token]
        );

        if (!$user) {
            return false;
        }

        $this->db->update('users', [
            'email_verified' => 1,
            'verification_token' => null
        ], 'id = :id', ['id' => $user['id']]);

        Logger::info("Email verified for user ID: {$user['id']}");
        return true;
    }

    /**
     * Create password reset token
     * 
     * @param string $email User email
     * @return string|null Reset token or null
     */
    public function createResetToken(string $email): ?string
    {
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = :email AND status = 'active'",
            ['email' => $email]
        );

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Delete any existing reset tokens for this user
        $this->db->delete('password_resets', 'user_id = :user_id', ['user_id' => $user['id']]);

        // Create new reset token
        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expiry
        ]);

        return $token;
    }

    /**
     * Reset password with token
     * 
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return bool True on success
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $reset = $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW()",
            ['token' => $token]
        );

        if (!$reset) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, [
            'cost' => BCRYPT_COST
        ]);

        // Update password
        $this->db->update('users', [
            'password_hash' => $hashedPassword
        ], 'id = :id', ['id' => $reset['user_id']]);

        // Delete reset token
        $this->db->delete('password_resets', 'id = :id', ['id' => $reset['id']]);

        Logger::info("Password reset for user ID: {$reset['user_id']}");
        return true;
    }
}
