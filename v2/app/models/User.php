<?php
/**
 * User Model
 * 
 * Handles all user-related database operations.
 * 
 * @package App\Models
 */

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return array|null User data
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        return $this->query($sql, ['email' => $email])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create new user
     * 
     * @param array $data User data
     * @return int|null New user ID or null
     */
    public function createUser(array $data): ?int
    {
        $password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        $sql = "INSERT INTO {$this->table} (fullname, email, password_hash, role, status, email_verified)
                VALUES (:fullname, :email, :password_hash, :role, :status, :email_verified)";
        
        $this->query($sql, [
            'fullname' => $data['fullname'],
            'email' => $data['email'],
            'password_hash' => $password,
            'role' => $data['role'] ?? 'user',
            'status' => $data['status'] ?? 'active',
            'email_verified' => $data['email_verified'] ?? 0
        ]);

        $userId = (int) $this->db->getConnection()->lastInsertId();
        return $userId > 0 ? $userId : null;
    }

    /**
     * Verify user email
     * 
     * @param string $token Verification token
     * @return bool True if verified
     */
    public function verifyEmail(string $token): bool
    {
        $sql = "UPDATE {$this->table} 
                SET email_verified = 1, verification_token = NULL 
                WHERE verification_token = :token";
        $stmt = $this->query($sql, ['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update last login timestamp
     * 
     * @param int $userId User ID
     * @return bool True on success
     */
    public function updateLastLogin(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all users with pagination
     * 
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string $search Search term
     * @return array Users and pagination
     */
    public function getUsers(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $where = '';
        $params = [];

        if ($search) {
            $where = "WHERE (fullname LIKE :search OR email LIKE :search2)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $total = $this->query($countSql, $params)->fetchColumn();

        // Get users
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT id, fullname, email, role, status, email_verified, last_login, created_at 
                FROM {$this->table} {$where} 
                ORDER BY created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}";
        
        $users = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users' => $users,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Update user
     * 
     * @param int $userId User ID
     * @param array $data Update data
     * @return bool True on success
     */
    public function updateUser(int $userId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $userId];

        if (isset($data['fullname'])) {
            $fields[] = 'fullname = :fullname';
            $params['fullname'] = $data['fullname'];
        }

        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params['email'] = $data['email'];
        }

        if (isset($data['role'])) {
            $fields[] = 'role = :role';
            $params['role'] = $data['role'];
        }

        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool True on success
     */
    public function changePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $sql = "UPDATE {$this->table} SET password_hash = :hash WHERE id = :id";
        $stmt = $this->query($sql, ['hash' => $hash, 'id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete user
     * 
     * @param int $userId User ID
     * @return bool True on success
     */
    public function deleteUser(int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user count by status
     * 
     * @return array User counts
     */
    public function getCountByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status";
        $results = $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = ['active' => 0, 'banned' => 0, 'total' => 0];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
            $counts['total'] += (int) $row['count'];
        }
        
        return $counts;
    }

    /**
     * Get user count by role
     * 
     * @return array Role counts
     */
    public function getCountByRole(): array
    {
        $sql = "SELECT role, COUNT(*) as count FROM {$this->table} GROUP BY role";
        $results = $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = ['admin' => 0, 'user' => 0];
        foreach ($results as $row) {
            $counts[$row['role']] = (int) $row['count'];
        }
        
        return $counts;
    }

    /**
     * Search users
     * 
     * @param string $query Search query
     * @param int $limit Max results
     * @return array Matching users
     */
    public function search(string $query, int $limit = 10): array
    {
        $sql = "SELECT id, fullname, email, role 
                FROM {$this->table} 
                WHERE (fullname LIKE :q1 OR email LIKE :q2) 
                LIMIT {$limit}";
        
        return $this->query($sql, ['q1' => "%{$query}%", 'q2' => "%{$query}%"])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent users
     * 
     * @param int $limit Number of users to get
     * @return array Recent users
     */
    public function getRecent(int $limit = 5): array
    {
        $sql = "SELECT id, fullname, email, role, created_at 
                FROM {$this->table} 
                ORDER BY created_at DESC 
                LIMIT {$limit}";
        
        return $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get dashboard statistics
     * 
     * @return array Dashboard stats
     */
    public function getDashboardStats(): array
    {
        $countSql = "SELECT COUNT(*) FROM {$this->table}";
        $total = $this->query($countSql)->fetchColumn();

        $todaySql = "SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = CURDATE()";
        $today = $this->query($todaySql)->fetchColumn();

        $activeSql = "SELECT COUNT(*) FROM {$this->table} WHERE status = 'active'";
        $active = $this->query($activeSql)->fetchColumn();

        return [
            'total_users' => (int) $total,
            'today_users' => (int) $today,
            'active_users' => (int) $active
        ];
    }
}
