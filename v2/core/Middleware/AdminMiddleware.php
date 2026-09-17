<?php
/**
 * Admin Middleware
 * 
 * Checks if user has admin privileges.
 * Must be used after AuthMiddleware.
 * 
 * @package Core\Middleware
 */

class AdminMiddleware
{
    /**
     * Handle middleware
     * 
     * @return bool True if request should proceed
     */
    public function handle(): bool
    {
        if (!Auth::check()) {
            Session::flash('warning', 'Please log in to continue.');
            header('Location: /login');
            exit;
        }

        if (!Session::isAdmin()) {
            Session::flash('error', 'Access denied. Admin privileges required.');
            header('Location: /dashboard');
            exit;
        }

        return true;
    }
}
