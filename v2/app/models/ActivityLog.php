<?php
/**
 * Activity Log Model
 * 
 * Tracks user activity and system events.
 * 
 * @package App\Models
 */

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';
    protected string $primaryKey = 'id';

    /**
     * Log activity
     * 
     * @param int|null $userId User ID (null for system actions)
     * @param string $action Action performed
     * @return bool True on success
     */
    public function log(?int $userId, string $action): bool
    {
        $sql = "INSERT INTO {$this->table} (user_id, action, ip_address, user_agent) 
                VALUES (:user_id, :action, :ip_address, :user_agent)";
        
        $stmt = $this->query($sql, [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => Security::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get recent activities
     * 
     * @param int $limit Number of activities
     * @param int|null $userId Filter by user ID
     * @return array Activities
     */
    public function getRecent(int $limit = 50, ?int $userId = null): array
    {
        $where = '';
        $params = [];

        if ($userId !== null) {
            $where = "WHERE al.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql = "SELECT al.*, u.fullname, u.email 
                FROM {$this->table} al 
                LEFT JOIN users u ON al.user_id = u.id 
                {$where} 
                ORDER BY al.created_at DESC 
                LIMIT {$limit}";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity count by user
     * 
     * @param int $userId User ID
     * @param string|null $startDate Start date
     * @param string|null $endDate End date
     * @return int Activity count
     */
    public function getCountByUser(int $userId, ?string $startDate = null, ?string $endDate = null): int
    {
        $where = "WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        if ($startDate) {
            $where .= " AND created_at >= :start_date";
            $params['start_date'] = $startDate;
        }

        if ($endDate) {
            $where .= " AND created_at <= :end_date";
            $params['end_date'] = $endDate;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        return (int) $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Get daily activity stats
     * 
     * @param int $days Number of days to look back
     * @return array Daily stats
     */
    public function getDailyStats(int $days = 7): array
    {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM {$this->table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC";

        return $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear old activities
     * 
     * @param int $days Keep activities for this many days
     * @return int Number of deleted records
     */
    public function clearOld(int $days = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        
        $stmt = $this->query($sql, []);
        return $stmt->rowCount();
    }

    /**
     * Get top actions
     * 
     * @param int $limit Number of top actions
     * @return array Top actions
     */
    public function getTopActions(int $limit = 10): array
    {
        $sql = "SELECT action, COUNT(*) as count 
                FROM {$this->table} 
                GROUP BY action 
                ORDER BY count DESC 
                LIMIT {$limit}";

        return $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
