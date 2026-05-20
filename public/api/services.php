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