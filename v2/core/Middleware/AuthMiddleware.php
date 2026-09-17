<?php
/**
 * Authentication Middleware
 * 
 * Checks if user is authenticated before accessing protected routes.
 * 
 * @package Core\Middleware
 */

class AuthMiddleware
{
    /**
     * Handle middleware
     * 
     * @return bool True if request should proceed
     */
    public function handle(): bool
    {
        if (!Auth::check()) {
            // Try remember me
            if (Auth::tryRememberMe()) {
                return true;
            }
            
            Session::flash('warning', 'Please log in to continue.');
            header('Location: /login');
            exit;
        }

        return true;
    }
}
