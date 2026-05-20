<?php
/**
 * Rate Limiting Setup Script for MAI Salon
 * Sets up rate limiting for public APIs
 */

require_once '../config/database.php';
require_once '../app/Middleware/RateLimitMiddleware.php';

use App\Middleware\RateLimitMiddleware;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "📋 Setting up Rate Limiting System...\n\n";
    
    // 1. Create rate limits directory
    echo "📁 Creating rate limits directory...\n";
    $rate_limits_dir = __DIR__ . '/../storage/rate_limits';
    
    if (!is_dir($rate_limits_dir)) {
        mkdir($rate_limits_dir, 0755, true);
        echo "  Created: $rate_limits_dir\n";
    } else {
        echo "  Rate limits directory already exists\n";
    }
    
    // 2. Create logs directory if it doesn't exist
    echo "📁 Creating logs directory...\n";
    $logs_dir = __DIR__ . '/../storage/logs';
    
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
        echo "  Created: $logs_dir\n";
    } else {
        echo "  Logs directory already exists\n";
    }
    
    // 3. Test rate limiting middleware
    echo "🔄 Testing rate limiting middleware...\n";
    $rate_limit = new RateLimitMiddleware($db, $rate_limits_dir);
    
    // Test with a dummy key
    $test_key = 'test_ip_' . time();
    $result = $rate_limit->check($test_key, 'public_api');
    
    if (isset($result['headers']['X-RateLimit-Limit']) && isset($result['headers']['X-RateLimit-Remaining'])) {
        echo "✅ Rate limiting middleware test successful\n";
        echo "  Rate limit: " . $result['headers']['X-RateLimit-Limit'] . "\n";
        echo "  Remaining: " . $result['headers']['X-RateLimit-Remaining'] . "\n";
        echo "  Reset: " . $result['headers']['X-RateLimit-Reset'] . " seconds\n\n";
    } else {
        echo "❌ Rate limiting middleware test failed\n\n";
        throw new Exception("Rate limiting middleware test failed");
    }
    
    // 4. Create public API wrapper with rate limiting
    echo "📝 Creating public API wrapper...\n";
    $api_wrapper = __DIR__ . '/../public/api/index.php';
    $api_dir = dirname($api_wrapper);
    
    if (!is_dir($api_dir)) {
        mkdir($api_dir, 0755, true);
        echo "  Created API directory: $api_dir\n";
    }
    
    $wrapper_content = <<<'EOT'
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
EOT;
    
    file_put_contents($api_wrapper, $wrapper_content);
    echo "  Created: $api_wrapper\n\n";
    
    // 5. Create booking API endpoint with rate limiting
    echo "📝 Creating booking API endpoint...\n";
    $booking_api = __DIR__ . '/../public/api/booking.php';
    
    $booking_content = <<<'EOT'
<?php
/**
 * Public Booking API Endpoint for MAI Salon
 * Rate limited by the API wrapper
 */

// This file is included by the API wrapper, so database connection is already available

// Only allow POST requests for booking
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'method_not_allowed',
        'message' => 'Only POST method is allowed for booking'
    ]);
    exit;
}

try {
    // Get request body
    $request_body = file_get_contents('php://input');
    $data = json_decode($request_body, true);
    
    // Validate required fields
    $required_fields = ['customer_name', 'customer_phone', 'service_id', 'booking_date', 'booking_time'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'missing_fields',
            'message' => 'Required fields are missing',
            'fields' => $missing_fields
        ]);
        exit;
    }
    
    // Validate phone number format
    if (!preg_match('/^05\d{8}$/', $data['customer_phone'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'invalid_phone',
            'message' => 'Phone number must be in format 05XXXXXXXX'
        ]);
        exit;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['booking_date'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'invalid_date',
            'message' => 'Date must be in format YYYY-MM-DD'
        ]);
        exit;
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $data['booking_time'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'invalid_time',
            'message' => 'Time must be in format HH:MM'
        ]);
        exit;
    }
    
    // Check if service exists
    $service_query = "SELECT * FROM services WHERE id = ? AND is_active = 1";
    $service_stmt = $db->prepare($service_query);
    $service_stmt->execute([$data['service_id']]);
    $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        http_response_code(400);
        echo json_encode([
            'error' => 'invalid_service',
            'message' => 'Service not found or inactive'
        ]);
        exit;
    }
    
    // Check if specialist exists (if provided)
    $specialist_id = $data['specialist_id'] ?? null;
    if ($specialist_id) {
        $specialist_query = "SELECT * FROM team_members WHERE id = ? AND is_active = 1";
        $specialist_stmt = $db->prepare($specialist_query);
        $specialist_stmt->execute([$specialist_id]);
        $specialist = $specialist_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$specialist) {
            http_response_code(400);
            echo json_encode([
                'error' => 'invalid_specialist',
                'message' => 'Specialist not found or inactive'
            ]);
            exit;
        }
    }
    
    // Calculate appointment duration
    $duration = $service['duration'] ?? 60; // Default 60 minutes
    
    // Calculate start and end times
    $start_at = $data['booking_date'] . ' ' . $data['booking_time'] . ':00';
    $end_at = date('Y-m-d H:i:s', strtotime($start_at) + ($duration * 60));
    
    // Check for specialist availability if specialist is provided
    if ($specialist_id) {
        $availability_query = "
            SELECT COUNT(*) FROM appointments 
            WHERE specialist_id = ? 
            AND status IN ('pending', 'confirmed') 
            AND (
                (start_at <= ? AND end_at > ?) OR
                (start_at < ? AND end_at >= ?) OR
                (start_at >= ? AND end_at <= ?)
            )
        ";
        $availability_stmt = $db->prepare($availability_query);
        $availability_stmt->execute([
            $specialist_id, 
            $start_at, $start_at,
            $end_at, $end_at,
            $start_at, $end_at
        ]);
        
        if ($availability_stmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode([
                'error' => 'specialist_unavailable',
                'message' => 'Specialist is not available at the selected time'
            ]);
            exit;
        }
    }
    
    // Check if customer exists or create new one
    $customer_query = "SELECT * FROM customers WHERE phone = ?";
    $customer_stmt = $db->prepare($customer_query);
    $customer_stmt->execute([$data['customer_phone']]);
    $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    $customer_id = null;
    if ($customer) {
        $customer_id = $customer['id'];
    } else {
        // Create new customer
        $create_customer_query = "INSERT INTO customers (name, phone) VALUES (?, ?)";
        $create_customer_stmt = $db->prepare($create_customer_query);
        $create_customer_stmt->execute([$data['customer_name'], $data['customer_phone']]);
        $customer_id = $db->lastInsertId();
    }
    
    // Create appointment
    $appointment_query = "
        INSERT INTO appointments (
            customer_id, service_id, specialist_id, 
            start_at, end_at, status, 
            notes, source, created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', ?, 'public', NOW())
    ";
    $appointment_stmt = $db->prepare($appointment_query);
    $appointment_stmt->execute([
        $customer_id,
        $data['service_id'],
        $specialist_id,
        $start_at,
        $end_at,
        $data['notes'] ?? null
    ]);
    
    $appointment_id = $db->lastInsertId();
    
    // Create notification for admin
    $notification_query = "
        INSERT INTO appointment_notifications (
            appointment_id, message, is_read, created_at
        ) VALUES (?, ?, 0, NOW())
    ";
    $notification_stmt = $db->prepare($notification_query);
    $notification_stmt->execute([
        $appointment_id,
        'تم إنشاء موعد جديد من خلال الموقع الإلكتروني'
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'تم حجز الموعد بنجاح، سيتم التواصل معك قريباً للتأكيد',
        'appointment_id' => $appointment_id,
        'appointment_date' => $data['booking_date'],
        'appointment_time' => $data['booking_time']
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Booking API Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'An internal server error occurred'
    ]);
}
EOT;
    
    file_put_contents($booking_api, $booking_content);
    echo "  Created: $booking_api\n\n";
    
    // 6. Create services API endpoint with rate limiting
    echo "📝 Creating services API endpoint...\n";
    $services_api = __DIR__ . '/../public/api/services.php';
    
    $services_content = <<<'EOT'
<?php
/**
 * Public Services API Endpoint for MAI Salon
 * Rate limited by the API wrapper
 */

// This file is included by the API wrapper, so database connection is already available

// Only allow GET requests for services
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => 'method_not_allowed',
        'message' => 'Only GET method is allowed for services'
    ]);
    exit;
}

try {
    // Get query parameters
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 20; // Max 50 items
    
    // Build query
    $query = "
        SELECT s.*, c.name_ar as category_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        WHERE s.is_active = 1
    ";
    
    $params = [];
    
    if ($category_id) {
        $query .= " AND s.category_id = ?";
        $params[] = $category_id;
    }
    
    $query .= " ORDER BY s.sort_order, s.title_ar LIMIT ?";
    $params[] = $limit;
    
    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories
    $categories_query = "SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name_ar";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return response
    echo json_encode([
        'success' => true,
        'services' => $services,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Services API Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'An internal server error occurred'
    ]);
}
EOT;
    
    file_put_contents($services_api, $services_content);
    echo "  Created: $services_api\n\n";
    
    // 7. Create specialists API endpoint with rate limiting
    echo "📝 Creating specialists API endpoint...\n";
    $specialists_api = __DIR__ . '/../public/api/specialists.php';
    
    $specialists_content = <<<'EOT'
<?php
/**
 * Public Specialists API Endpoint for MAI Salon
 * Rate limited by the API wrapper
 */

// This file is included by the API wrapper, so database connection is already available

// Only allow GET requests for specialists
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => 'method_not_allowed',
        'message' => 'Only GET method is allowed for specialists'
    ]);
    exit;
}

try {
    // Get query parameters
    $service_id = isset($_GET['service']) ? intval($_GET['service']) : null;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 20; // Max 50 items
    
    // Build query
    $query = "
        SELECT id, name_ar, name_en, position_ar, position_en, bio_ar, bio_en, 
               image, experience_years
        FROM team_members
        WHERE is_active = 1
    ";
    
    // Add service filter if provided
    if ($service_id) {
        $query = "
            SELECT t.id, t.name_ar, t.name_en, t.position_ar, t.position_en, 
                   t.bio_ar, t.bio_en, t.image, t.experience_years
            FROM team_members t
            JOIN specialist_services ss ON t.id = ss.specialist_id
            WHERE t.is_active = 1 AND ss.service_id = ?
        ";
    }
    
    $query .= " ORDER BY sort_order, name_ar LIMIT ?";
    
    // Execute query
    $stmt = $db->prepare($query);
    
    if ($service_id) {
        $stmt->execute([$service_id, $limit]);
    } else {
        $stmt->execute([$limit]);
    }
    
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return response
    echo json_encode([
        'success' => true,
        'specialists' => $specialists
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Specialists API Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'message' => 'An internal server error occurred'
    ]);
}
EOT;
    
    file_put_contents($specialists_api, $specialists_content);
    echo "  Created: $specialists_api\n\n";
    
    echo "🎉 Rate Limiting System setup completed successfully!\n";
    echo "Public APIs are now protected with rate limiting.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>
