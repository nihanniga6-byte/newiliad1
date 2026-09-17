<?php
/**
 * Site Setting Model
 * 
 * Manages site-wide settings with file-based caching.
 * 
 * @package App\Models
 */

class SiteSetting extends Model
{
    protected string $table = 'site_settings';
    protected string $primaryKey = 'id';

    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get(string $key, $default = null)
    {
        // Try cache first
        $cache = new Cache();
        $cached = $cache->get("setting_{$key}");
        if ($cached !== null) {
            return $cached;
        }

        // Query database
        $sql = "SELECT setting_value FROM {$this->table} WHERE setting_key = :key";
        $result = $this->query($sql, ['key' => $key])->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $value = json_decode($result['setting_value'], true);
            $cache->set("setting_{$key}", $value, CACHE_LIFETIME);
            return $value;
        }

        return $default;
    }

    /**
     * Set setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool True on success
     */
    public function set(string $key, $value): bool
    {
        $jsonValue = json_encode($value);

        // Check if exists
        $checkSql = "SELECT id FROM {$this->table} WHERE setting_key = :key";
        $exists = $this->query($checkSql, ['key' => $key])->fetch();

        if ($exists) {
            $sql = "UPDATE {$this->table} SET setting_value = :value WHERE setting_key = :key";
            $params = ['value' => $jsonValue, 'key' => $key];
        } else {
            $sql = "INSERT INTO {$this->table} (setting_key, setting_value) VALUES (:key, :value)";
            $params = ['key' => $key, 'value' => $jsonValue];
        }

        $stmt = $this->query($sql, $params);

        // Clear cache
        $cache = new Cache();
        $cache->delete("setting_{$key}");

        return $stmt->rowCount() > 0;
    }

    /**
     * Get all settings
     * 
     * @return array All settings
     */
    public function getAll(): array
    {
        $cache = new Cache();
        $cached = $cache->get('all_settings');
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT setting_key, setting_value FROM {$this->table}";
        $results = $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
        }

        $cache->set('all_settings', $settings, CACHE_LIFETIME);
        return $settings;
    }

    /**
     * Delete setting
     * 
     * @param string $key Setting key
     * @return bool True on success
     */
    public function delete(string $key): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE setting_key = :key";
        $stmt = $this->query($sql, ['key' => $key]);

        // Clear cache
        $cache = new Cache();
        $cache->delete("setting_{$key}");
        $cache->delete('all_settings');

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if setting exists
     * 
     * @param string $key Setting key
     * @return bool True if exists
     */
    public function exists(string $key): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE setting_key = :key";
        return (int) $this->query($sql, ['key' => $key])->fetchColumn() > 0;
    }

    /**
     * Get site name
     * 
     * @return string Site name
     */
    public function getSiteName(): string
    {
        return $this->get('site_name', 'Dr. Zohrabi Nutrition Clinic');
    }

    /**
     * Check if maintenance mode is on
     * 
     * @return bool True if maintenance mode
     */
    public function isMaintenanceMode(): bool
    {
        return $this->get('maintenance_mode', false) === true;
    }
}
