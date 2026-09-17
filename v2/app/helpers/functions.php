<?php
/**
 * Helper Functions
 * 
 * Common utility functions used throughout the application.
 * 
 * @package Helpers
 */

/**
 * Escape output data (Anti-XSS)
 * 
 * @param string $data Data to escape
 * @return string Escaped data safe for HTML output
 */
function e(string $data): string
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Get CSRF token field for forms
 * 
 * @return string HTML hidden input with CSRF token
 */
function csrf_field(): string
{
    return Security::csrfField();
}

/**
 * Get CSRF token value
 * 
 * @return string CSRF token
 */
function csrf_token(): string
{
    return Security::csrfToken();
}

/**
 * Redirect to URL
 * 
 * @param string $url Target URL
 * @param int $statusCode HTTP status code
 */
function redirect(string $url, int $statusCode = 302): void
{
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Get URL base path
 * 
 * @return string Base URL
 */
function base_url(): string
{
    return APP_URL;
}

/**
 * Format file size
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Format date
 * 
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function format_date(string $date, string $format = 'M d, Y'): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date($format, $timestamp);
}

/**
 * Time ago format
 * 
 * @param string $datetime DateTime string
 * @return string Time ago string
 */
function time_ago(string $datetime): string
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return format_date($datetime);
}

/**
 * Generate UUID
 * 
 * @return string UUID v4
 */
function generate_uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Truncate text
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add if truncated
 * @return string Truncated text
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Check if current route requires auth
 * 
 * @return bool True if auth required
 */
function requires_auth(): bool
{
    $publicRoutes = ['/', '/login', '/register', '/forgot-password', '/reset-password'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/') ?: '/';
    
    return !in_array($uri, $publicRoutes);
}

/**
 * Get current user
 * 
 * @return array|null User data
 */
function current_user(): ?array
{
    return Auth::user();
}

/**
 * Check if current user is admin
 * 
 * @return bool True if admin
 */
function is_admin(): bool
{
    return Session::isAdmin();
}

/**
 * Sanitize input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize(string $input): string
{
    return Security::sanitize($input);
}

/**
 * Flash message helper
 * 
 * @param string $type Message type
 * @param string $message Message text
 */
function set_flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/**
 * Get flash message
 * 
 * @param string $type Message type
 * @return string|null Message text
 */
function get_flash(string $type): ?string
{
    return Session::getFlash($type);
}
