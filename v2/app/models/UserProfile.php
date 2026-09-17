<?php
/**
 * UserProfile Model
 * 
 * Handles user profile operations.
 * 
 * @package App\Models
 */

class UserProfile extends Model
{
    protected string $table = 'user_profiles';
    protected string $primaryKey = 'user_id';

    /**
     * Get profile by user ID
     * 
     * @param int $userId User ID
     * @return array|null Profile data
     */
    public function getByUserId(int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id";
        return $this->query($sql, ['user_id' => $userId])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create or update profile
     * 
     * @param int $userId User ID
     * @param array $data Profile data
     * @return bool True on success
     */
    public function save(int $userId, array $data): bool
    {
        $existing = $this->getByUserId($userId);
        
        if ($existing) {
            // Update
            $fields = [];
            $params = ['user_id' => $userId];

            foreach ($data as $key => $value) {
                if (in_array($key, ['bio', 'avatar', 'phone', 'address'])) {
                    $fields[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }

            if (empty($fields)) {
                return false;
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
        } else {
            // Insert
            $sql = "INSERT INTO {$this->table} (user_id, bio, avatar, phone, address) 
                    VALUES (:user_id, :bio, :avatar, :phone, :address)";
            $params = [
                'user_id' => $userId,
                'bio' => $data['bio'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null
            ];
        }

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update avatar
     * 
     * @param int $userId User ID
     * @param string $avatarPath Avatar path
     * @return bool True on success
     */
    public function updateAvatar(int $userId, string $avatarPath): bool
    {
        $existing = $this->getByUserId($userId);
        
        if ($existing) {
            $sql = "UPDATE {$this->table} SET avatar = :avatar WHERE user_id = :user_id";
        } else {
            $sql = "INSERT INTO {$this->table} (user_id, avatar) VALUES (:user_id, :avatar)";
        }

        $stmt = $this->query($sql, ['avatar' => $avatarPath, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete profile
     * 
     * @param int $userId User ID
     * @return bool True on success
     */
    public function deleteProfile(int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->query($sql, ['user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }
}
