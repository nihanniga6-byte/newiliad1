<?php
/**
 * Auth Controller
 * 
 * Handles authentication: login, register, logout, password reset.
 * 
 * @package App\Controllers
 */

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    /**
     * Show login form
     */
    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        
        $this->view('auth.login', [
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success')
        ]);
    }

    /**
     * Process login
     */
    public function login(): void
    {
        $email = $this->input('email');
        $password = $this->input('password');
        $remember = $this->input('remember') === 'on';

        $errors = [];

        if (empty($email)) $errors[] = 'Email is required';
        if (empty($password)) $errors[] = 'Password is required';

        if (!empty($errors)) {
            $this->view('auth.login', [
                'error' => $errors[0],
                'old' => ['email' => $email]
            ]);
            return;
        }

        $auth = new Auth();
        $user = $auth->login($email, $password, $remember);

        if (!$user) {
            $this->view('auth.login', [
                'error' => 'Invalid email or password',
                'old' => ['email' => $email]
            ]);
            return;
        }

        Session::flash('success', 'Welcome back, ' . e($user['fullname']) . '!');

        // Redirect based on role
        if ($user['role'] === 'admin') {
            redirect('/admin/dashboard');
        } else {
            redirect('/dashboard');
        }
    }

    /**
     * Show registration form
     */
    public function registerForm(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        $this->view('auth.register', [
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success')
        ]);
    }

    /**
     * Process registration
     */
    public function register(): void
    {
        $fullname = $this->input('fullname');
        $email = $this->input('email');
        $password = $this->input('password');
        $confirmPassword = $this->input('confirm_password');

        $errors = [];

        if (empty($fullname)) $errors[] = 'Full name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';

        // Check if email exists
        if (empty($errors)) {
            $existingUser = $this->userModel->findByEmail($email);
            if ($existingUser) {
                $errors[] = 'Email address already registered';
            }
        }

        if (!empty($errors)) {
            $this->view('auth.register', [
                'error' => $errors[0],
                'old' => ['fullname' => $fullname, 'email' => $email]
            ]);
            return;
        }

        // Create user
        $userId = $this->userModel->createUser([
            'fullname' => $fullname,
            'email' => $email,
            'password' => $password,
            'role' => 'user',
            'email_verified' => 1 // Auto-verify for now
        ]);

        if ($userId) {
            // Create empty profile
            $profileModel = new UserProfile();
            $profileModel->save($userId, []);

            // Log activity
            ActivityLogger::log($userId, 'register');

            Session::flash('success', 'Registration successful! Please log in.');
            redirect('/login');
        } else {
            $this->view('auth.register', [
                'error' => 'Registration failed. Please try again.',
                'old' => ['fullname' => $fullname, 'email' => $email]
            ]);
        }
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        $userId = Session::userId();
        
        // Log activity
        if ($userId) {
            ActivityLogger::log($userId, 'logout');
        }

        $auth = new Auth();
        $auth->logout();
        redirect('/login');
    }

    /**
     * Show forgot password form
     */
    public function forgotPasswordForm(): void
    {
        $this->view('auth.forgot-password');
    }

    /**
     * Process forgot password
     */
    public function forgotPassword(): void
    {
        $email = $this->input('email');

        if (empty($email)) {
            $this->view('auth.forgot-password', [
                'error' => 'Email is required'
            ]);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        // Always show success message to prevent email enumeration
        $this->view('auth.forgot-password', [
            'success' => 'If your email is registered, you will receive a password reset link shortly.'
        ]);
    }

    /**
     * Show reset password form
     */
    public function resetPasswordForm(string $token): void
    {
        $this->view('auth.reset-password', [
            'token' => $token
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(string $token): void
    {
        $password = $this->input('password');
        $confirmPassword = $this->input('confirm_password');

        if (empty($password)) {
            $this->view('auth.reset-password', [
                'token' => $token,
                'error' => 'Password is required'
            ]);
            return;
        }

        if (strlen($password) < 8) {
            $this->view('auth.reset-password', [
                'token' => $token,
                'error' => 'Password must be at least 8 characters'
            ]);
            return;
        }

        if ($password !== $confirmPassword) {
            $this->view('auth.reset-password', [
                'token' => $token,
                'error' => 'Passwords do not match'
            ]);
            return;
        }

        // Verify token
        $resetModel = $this->loadModel('PasswordReset');
        $userId = $resetModel->verifyToken($token);

        if (!$userId) {
            $this->view('auth.reset-password', [
                'error' => 'Invalid or expired reset token'
            ]);
            return;
        }

        // Update password
        $this->userModel->changePassword($userId, $password);

        // Delete reset token
        $resetModel->deleteToken($token);

        // Log activity
        ActivityLogger::log($userId, 'password_reset');

        Session::flash('success', 'Password reset successful! Please log in with your new password.');
        redirect('/login');
    }
}
