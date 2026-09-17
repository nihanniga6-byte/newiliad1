<?php
/**
 * File-based Cache System
 * 
 * Improves performance by caching frequently executed queries.
 * Critical for shared hosting with limited CPU/RAM.
 * 
 * @package Core
 */

class Cache
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = CACHE_PATH;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached value by key
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null
     */
    public function get(string $key)
    {
        $file = $this->getCachePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        $cached = @unserialize($data);

        if ($cached === false) {
            unlink($file);
            return null;
        }

        // Check if cache has expired
        if (time() > $cached['expiry']) {
            $this->delete($key);
            return null;
        }

        return $cached['value'];
    }

    /**
     * Store value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool True on success
     */
    public function set(string $key, $value, int $ttl = CACHE_LIFETIME): bool
    {
        $file = $this->getCachePath($key);

        $cached = [
            'key' => $key,
            'value' => $value,
            'expiry' => time() + $ttl,
            'created' => date('Y-m-d H:i:s')
        ];

        return file_put_contents($file, serialize($cached), LOCK_EX) !== false;
    }

    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool True on success
     */
    public function delete(string $key): bool
    {
        $file = $this->getCachePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    /**
     * Check if key exists and is not expired
     * 
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Clear all cached files
     */
    public function clear(): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get or set (cache-aside pattern)
     * 
     * If value exists in cache, return it.
     * Otherwise, execute callback, cache result, and return it.
     * 
     * @param string $key Cache key
     * @param int $ttl Cache lifetime
     * @param callable $callback Function to execute if not cached
     * @return mixed Cached or computed value
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Get cache file path
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private function getCachePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        return $this->cacheDir . '/' . md5($safeKey) . '.cache';
    }

    /**
     * Remove expired cache files (garbage collection)
     */
    public function gc(): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            $data = file_get_contents($file);
            if ($data !== false) {
                $cached = @unserialize($data);
                if ($cached && time() > ($cached['expiry'] ?? 0)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = file_get_contents($file);
            if ($data !== false) {
                $cached = @unserialize($data);
                if ($cached && time() > ($cached['expiry'] ?? 0)) {
                    $expiredCount++;
                } else {
                    $validCount++;
                }
            }
        }

        return [
            'total_files' => count($files),
            'valid_files' => $validCount,
            'expired_files' => $expiredCount,
            'total_size' => $totalSize
        ];
    }
}
