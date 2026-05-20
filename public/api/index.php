<?php
/**
 * Public API Wrapper with Rate Limiting for MAI Salon
 * All public API requests should go through this wrapper
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Middleware/RateLimitMiddleware.php';

use App\Middleware\RateLimitMiddleware;

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS for public API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get client IP address
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Initialize rate limiting middleware
    $rate_limit = new RateLimitMiddleware($db);
    
    // Determine API type based on request path
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $api_type = 'public_api'; // Default
    
    if (strpos($request_uri, '/api/search') !== false) {
        $api_type = 'search_api';
    } elseif (strpos($request_uri, '/api/booking') !== false) {
        $api_type = 'booking_api';
    }
    
    // Apply rate limiting
    if (!$rate_limit->apply($client_ip, $api_type)) {
        // Rate limit exceeded, response already sent by middleware
        exit;
    }
    
    // Parse request path to determine which API to call
    $path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    $api_endpoint = $path_parts[count($path_parts) - 1] ?? '';
    
    // Route to appropriate API handler
    switch ($api_endpoint) {
        case 'booking':
            require_once __DIR__ . '/booking.php';
            break;
            
        case 'services':
            require_once __DIR__ . '/services.php';
            break;
            
        case 'specialists':
            require_once __DIR__ . '/specialists.php';
            break;
            
        default:
            // API endpoint not found
            http_response_code(404);
            echo json_encode([
                'error' => 'not_found',
                'message' => 'API endpoint not found'
            ]);
            break;
    }
    
} catch (Exception $e) {
    // Log error
    error_log('API Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'An internal server error occurred'
    ]);
}