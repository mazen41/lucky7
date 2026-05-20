<?php
/**
 * Rate Limit Middleware for MAI Salon
 * Implements token bucket algorithm for API rate limiting
 */

namespace App\Middleware;

class RateLimitMiddleware {
    private $db;
    private $storage_dir;
    
    // Default rate limit settings
    private $default_settings = [
        'public_api' => [
            'rate' => 60,       // 60 requests
            'per' => 60,        // per 60 seconds (1 minute)
            'burst' => 10       // allow 10 requests burst
        ],
        'search_api' => [
            'rate' => 30,       // 30 requests
            'per' => 60,        // per 60 seconds (1 minute)
            'burst' => 5        // allow 5 requests burst
        ],
        'booking_api' => [
            'rate' => 10,       // 10 requests
            'per' => 60,        // per 60 seconds (1 minute)
            'burst' => 3        // allow 3 requests burst
        ]
    ];
    
    /**
     * Constructor
     * 
     * @param object $db Database connection
     * @param string $storage_dir Storage directory for rate limit data
     */
    public function __construct($db, $storage_dir = null) {
        $this->db = $db;
        $this->storage_dir = $storage_dir ?? __DIR__ . '/../../storage/rate_limits';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir, 0755, true);
        }
    }
    
    /**
     * Check if request is rate limited
     * 
     * @param string $key Rate limit key (usually IP address)
     * @param string $type Rate limit type (public_api, search_api, booking_api)
     * @return array Result with status and headers
     */
    public function check($key, $type = 'public_api') {
        // Get settings for this type
        $settings = $this->default_settings[$type] ?? $this->default_settings['public_api'];
        
        // Get bucket file path
        $bucket_file = $this->getBucketFilePath($key, $type);
        
        // Initialize bucket or load existing one
        $bucket = $this->loadBucket($bucket_file, $settings);
        
        // Calculate tokens to add based on time passed
        $now = microtime(true);
        $time_passed = $now - $bucket['last_check'];
        $tokens_to_add = $time_passed * ($settings['rate'] / $settings['per']);
        
        // Add tokens to bucket (up to capacity)
        $bucket['tokens'] = min($bucket['tokens'] + $tokens_to_add, $settings['rate'] + $settings['burst']);
        $bucket['last_check'] = $now;
        
        // Check if request can be processed
        $can_process = $bucket['tokens'] >= 1;
        
        // Consume a token if request can be processed
        if ($can_process) {
            $bucket['tokens'] -= 1;
        }
        
        // Save updated bucket
        $this->saveBucket($bucket_file, $bucket);
        
        // Calculate rate limit headers
        $remaining = floor($bucket['tokens']);
        $reset = ceil(($settings['rate'] - $bucket['tokens']) * ($settings['per'] / $settings['rate']));
        $reset = max(0, $reset);
        
        // Return result with headers
        return [
            'limited' => !$can_process,
            'headers' => [
                'X-RateLimit-Limit' => $settings['rate'],
                'X-RateLimit-Remaining' => $remaining,
                'X-RateLimit-Reset' => $reset
            ]
        ];
    }
    
    /**
     * Get bucket file path
     * 
     * @param string $key Rate limit key
     * @param string $type Rate limit type
     * @return string File path
     */
    private function getBucketFilePath($key, $type) {
        $safe_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->storage_dir . '/' . $type . '_' . $safe_key . '.bucket';
    }
    
    /**
     * Load bucket from file or create new one
     * 
     * @param string $file_path Bucket file path
     * @param array $settings Rate limit settings
     * @return array Bucket data
     */
    private function loadBucket($file_path, $settings) {
        if (file_exists($file_path)) {
            $data = file_get_contents($file_path);
            return unserialize($data);
        }
        
        // Create new bucket
        return [
            'tokens' => $settings['rate'] + $settings['burst'],
            'last_check' => microtime(true)
        ];
    }
    
    /**
     * Save bucket to file
     * 
     * @param string $file_path Bucket file path
     * @param array $bucket Bucket data
     * @return bool True on success
     */
    private function saveBucket($file_path, $bucket) {
        $data = serialize($bucket);
        return file_put_contents($file_path, $data) !== false;
    }
    
    /**
     * Apply rate limit to request
     * 
     * @param string $key Rate limit key
     * @param string $type Rate limit type
     * @return bool True if request can proceed
     */
    public function apply($key, $type = 'public_api') {
        $result = $this->check($key, $type);
        
        if ($result['limited']) {
            // Set rate limit headers
            foreach ($result['headers'] as $header => $value) {
                header("$header: $value");
            }
            
            // Set 429 Too Many Requests status code
            http_response_code(429);
            
            // Return JSON error response
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'rate_limit_exceeded',
                'message' => 'تم تجاوز الحد المسموح من الطلبات، يرجى المحاولة مرة أخرى لاحقاً',
                'retry_after' => $result['headers']['X-RateLimit-Reset']
            ]);
            
            // Log rate limit hit
            $this->logRateLimitHit($key, $type);
            
            return false;
        }
        
        // Set rate limit headers
        foreach ($result['headers'] as $header => $value) {
            header("$header: $value");
        }
        
        return true;
    }
    
    /**
     * Log rate limit hit
     * 
     * @param string $key Rate limit key
     * @param string $type Rate limit type
     */
    private function logRateLimitHit($key, $type) {
        $log_file = __DIR__ . '/../../storage/logs/rate_limit.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_message = date('Y-m-d H:i:s') . " - Rate limit exceeded for $key on $type API\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
