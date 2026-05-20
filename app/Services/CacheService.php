<?php
/**
 * Cache Service for MAI Salon
 * Provides caching functionality for dashboard KPIs and other frequently accessed data
 */

namespace App\Services;

class CacheService {
    private $cache_dir;
    private $default_ttl = 300; // 5 minutes default TTL
    
    /**
     * Constructor
     * 
     * @param string $cache_dir Cache directory path
     */
    public function __construct($cache_dir = null) {
        $this->cache_dir = $cache_dir ?? __DIR__ . '/../../storage/cache';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Get cache key file path
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private function getCacheFilePath($key) {
        $safe_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cache_dir . '/' . $safe_key . '.cache';
    }
    
    /**
     * Check if cache exists and is valid
     * 
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @return bool True if cache exists and is valid
     */
    public function has($key, $ttl = null) {
        $file_path = $this->getCacheFilePath($key);
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $ttl = $ttl ?? $this->default_ttl;
        $file_time = filemtime($file_path);
        
        return (time() - $file_time) < $ttl;
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if cache doesn't exist
     * @param int $ttl Time to live in seconds
     * @return mixed Cached data or default value
     */
    public function get($key, $default = null, $ttl = null) {
        if (!$this->has($key, $ttl)) {
            return $default;
        }
        
        $file_path = $this->getCacheFilePath($key);
        $data = file_get_contents($file_path);
        
        return unserialize($data);
    }
    
    /**
     * Store data in cache
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @return bool True on success
     */
    public function put($key, $data) {
        $file_path = $this->getCacheFilePath($key);
        $serialized = serialize($data);
        
        return file_put_contents($file_path, $serialized) !== false;
    }
    
    /**
     * Get data from cache or store it if it doesn't exist
     * 
     * @param string $key Cache key
     * @param callable $callback Function to generate data if cache doesn't exist
     * @param int $ttl Time to live in seconds
     * @return mixed Cached data or generated data
     */
    public function remember($key, $callback, $ttl = null) {
        if ($this->has($key, $ttl)) {
            return $this->get($key);
        }
        
        $data = $callback();
        $this->put($key, $data);
        
        return $data;
    }
    
    /**
     * Remove cached data
     * 
     * @param string $key Cache key
     * @return bool True on success
     */
    public function forget($key) {
        $file_path = $this->getCacheFilePath($key);
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true;
    }
    
    /**
     * Clear all cached data
     * 
     * @return bool True on success
     */
    public function flush() {
        $files = glob($this->cache_dir . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function getStats() {
        $files = glob($this->cache_dir . '/*.cache');
        $total_size = 0;
        $oldest_time = time();
        $newest_time = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $file_time = filemtime($file);
            $oldest_time = min($oldest_time, $file_time);
            $newest_time = max($newest_time, $file_time);
        }
        
        return [
            'total_items' => count($files),
            'total_size' => $total_size,
            'oldest_item_age' => $oldest_time ? time() - $oldest_time : 0,
            'newest_item_age' => $newest_time ? time() - $newest_time : 0
        ];
    }
}
