<?php
/**
 * Activity Logger Helper
 * 
 * Static helper to log user activity via ActivityLog model.
 * 
 * @package Core
 */

class ActivityLogger
{
    /**
     * Log a user activity
     * 
     * @param int|null $userId User ID
     * @param string $action Action performed
     * @return void
     */
    public static function log(?int $userId, string $action): void
    {
        try {
            $log = new ActivityLog();
            $log->log($userId, $action);
        } catch (Exception $e) {
            Logger::error("Failed to log activity: " . $e->getMessage());
        }
    }
}
