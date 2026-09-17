<?php
/**
 * CSRF Middleware
 * 
 * Validates CSRF token for POST/PUT/DELETE requests.
 * 
 * @package Core\Middleware
 */

class CSRFMiddleware
{
    /**
     * Handle middleware
     * 
     * @return bool True if request should proceed
     */
    public function handle(): bool
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!Security::verifyCsrfToken()) {
                Session::flash('error', 'Invalid security token. Please try again.');
                
                // Redirect back to previous page or home
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                header("Location: {$referer}");
                exit;
            }
        }

        return true;
    }
}
