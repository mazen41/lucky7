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