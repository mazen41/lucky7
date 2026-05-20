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