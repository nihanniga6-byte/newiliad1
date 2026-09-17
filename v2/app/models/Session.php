<?php
/**
 * Session Model
 * 
 * Manages user sessions in database.
 * 
 * @package App\Models
 */

class Session extends Model
{
    protected string $table = 'sessions';
    protected string $primaryKey = 'id';

    /**
     * Create new session
     * 
     * @param array $data Session data
     * @return bool True on success
     */
    public function createSession(array $data): bool
    {
        $sql = "INSERT INTO {$this->table} (id, user_id, session_token, ip_address, user_agent, payload, last_activity) 
                VALUES (:id, :user_id, :session_token, :ip_address, :user_agent, :payload, :last_activity)";
        
        $stmt = $this->query($sql, [
            'id' => session_id(),
            'user_id' => $data['user_id'],
            'session_token' => $data['session_token'] ?? bin2hex(random_bytes(32)),
            'ip_address' => Security::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'payload' => serialize($data['payload'] ?? []),
            'last_activity' => time()
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update session activity
     * 
     * @param string $sessionId Session ID
     * @return bool True on success
     */
    public function updateActivity(string $sessionId): bool
    {
        $sql = "UPDATE {$this->table} SET last_activity = :time WHERE id = :id";
        $stmt = $this->query($sql, ['time' => time(), 'id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user sessions
     * 
     * @param int $userId User ID
     * @return array User sessions
     */
    public function getUserSessions(int $userId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY last_activity DESC";

        return $this->query($sql, ['user_id' => $userId])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete session
     * 
     * @param string $sessionId Session ID
     * @return bool True on success
     */
    public function deleteSession(string $sessionId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all user sessions
     * 
     * @param int $userId User ID
     * @return int Number of deleted sessions
     */
    public function deleteUserSessions(int $userId): int
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->query($sql, ['user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Delete expired sessions
     * 
     * @param int $maxLifetime Maximum session lifetime in seconds
     * @return int Number of deleted sessions
     */
    public function deleteExpired(int $maxLifetime = 1800): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE last_activity < :cutoff";
        
        $stmt = $this->query($sql, ['cutoff' => time() - $maxLifetime]);
        return $stmt->rowCount();
    }

    /**
     * Get active session count
     * 
     * @return int Active session count
     */
    public function getActiveCount(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        return (int) $this->query($sql)->fetchColumn();
    }

    /**
     * Get active user count (unique users with sessions)
     * 
     * @return int Active user count
     */
    public function getActiveUserCount(): int
    {
        $sql = "SELECT COUNT(DISTINCT user_id) FROM {$this->table}";
        return (int) $this->query($sql)->fetchColumn();
    }
}
