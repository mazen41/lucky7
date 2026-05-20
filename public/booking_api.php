<?php
/**
 * Public Booking API - External appointment booking endpoint
 * POST /public/booking
 */

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helper function to get site setting (guarded)
if (!function_exists('getSiteSetting')) {
    function getSiteSetting($key, $default = null) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Helper function to check if public booking is enabled
function isPublicBookingEnabled() {
    return getSiteSetting('appointment_public_booking', '1') === '1';
}

// Helper function to check specialist availability
function checkSpecialistAvailability($specialist_id, $start_at, $end_at) {
    global $db;
    
    $query = "SELECT COUNT(*) as conflicts FROM appointments 
              WHERE specialist_id = ? 
              AND status NOT IN ('cancelled', 'no_show')
              AND (
                  (start_at <= ? AND end_at > ?) OR
                  (start_at < ? AND end_at >= ?) OR
                  (start_at >= ? AND start_at < ?)
              )";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$specialist_id, $start_at, $start_at, $end_at, $end_at, $start_at, $end_at]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['conflicts'] == 0;
}

// Helper function to validate working hours
function isWithinWorkingHours($datetime) {
    $start_time = getSiteSetting('appointment_working_hours_start', '09:00');
    $end_time = getSiteSetting('appointment_working_hours_end', '17:00');
    
    $appointment_time = date('H:i', strtotime($datetime));
    $day_of_week = date('w', strtotime($datetime)); // 0 = Sunday, 6 = Saturday
    
    // Check if it's within working hours
    if ($appointment_time < $start_time || $appointment_time >= $end_time) {
        return false;
    }
    
    // Check if it's a working day (assuming Sunday-Thursday + Saturday)
    if ($day_of_week == 5) { // Friday
        $friday_start = getSiteSetting('appointment_friday_start', '14:00');
        $friday_end = getSiteSetting('appointment_friday_end', '22:00');
        return $appointment_time >= $friday_start && $appointment_time < $friday_end;
    }
    
    return true;
}

// Helper function to send notification
function sendBookingNotification($appointment_id, $type = 'new_booking') {
    global $db;
    
    try {
        $stmt = $db->prepare("INSERT INTO appointment_notifications 
                             (appointment_id, type, method, recipient, message, status) 
                             VALUES (?, ?, 'internal', 'admin', ?, 'pending')");
        
        $message = $type === 'new_booking' ? 
                  "موعد جديد من الحجز العام - يحتاج للمراجعة والتأكيد" : 
                  "تحديث على موعد من الحجز العام";
        
        $stmt->execute([$appointment_id, $type, $message]);
    } catch (Exception $e) {
        error_log("Failed to send booking notification: " . $e->getMessage());
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير مدعومة');
    }
    
    // Check if public booking is enabled
    if (!isPublicBookingEnabled()) {
        throw new Exception('الحجز العام غير متاح حالياً');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات الحجز غير صحيحة');
    }
    
    // Validate required fields
    $required_fields = ['customer_name', 'customer_phone', 'service_id', 'specialist_id', 'preferred_date', 'preferred_time'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("الحقل {$field} مطلوب");
        }
    }
    
    // Validate phone number format
    if (!preg_match('/^05[0-9]{8}$/', $input['customer_phone'])) {
        throw new Exception('رقم الهاتف غير صحيح. يجب أن يبدأ بـ 05 ويتكون من 10 أرقام');
    }
    
    // Validate email if provided
    if (!empty($input['customer_email']) && !filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('البريد الإلكتروني غير صحيح');
    }
    
    // Check advance booking limit
    $advance_days = intval(getSiteSetting('appointment_advance_booking_days', '30'));
    $preferred_datetime = $input['preferred_date'] . ' ' . $input['preferred_time'];
    $booking_date = new DateTime($preferred_datetime);
    $now = new DateTime();
    $max_date = clone $now;
    $max_date->add(new DateInterval('P' . $advance_days . 'D'));
    
    if ($booking_date <= $now) {
        throw new Exception('لا يمكن الحجز في الماضي');
    }
    
    if ($booking_date > $max_date) {
        throw new Exception("لا يمكن الحجز أكثر من {$advance_days} يوم مقدماً");
    }
    
    // Validate working hours
    if (!isWithinWorkingHours($preferred_datetime)) {
        throw new Exception('الوقت المحدد خارج ساعات العمل');
    }
    
    // Get service details
    $service_stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND is_bookable = 1");
    $service_stmt->execute([$input['service_id']]);
    $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        throw new Exception('الخدمة المحددة غير متاحة للحجز العام');
    }
    
    // Get specialist details
    $specialist_stmt = $db->prepare("SELECT * FROM team_members WHERE id = ? AND is_specialist = 1 AND is_available = 1");
    $specialist_stmt->execute([$input['specialist_id']]);
    $specialist = $specialist_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        throw new Exception('الأخصائي المحدد غير متاح');
    }
    
    // Calculate end time
    $start_at = new DateTime($preferred_datetime);
    $duration = $service['duration_min'] ?? 60;
    $buffer_time = intval(getSiteSetting('appointment_buffer_time', '15'));
    $end_at = clone $start_at;
    $end_at->add(new DateInterval('PT' . ($duration + $buffer_time) . 'M'));
    
    // Check specialist availability
    if (!checkSpecialistAvailability($input['specialist_id'], $start_at->format('Y-m-d H:i:s'), $end_at->format('Y-m-d H:i:s'))) {
        throw new Exception('الأخصائي غير متاح في هذا الوقت. يرجى اختيار وقت آخر');
    }
    
    $db->beginTransaction();
    
    try {
        // Handle customer
        $customer_id = null;
        
        // Check if customer exists
        $customer_stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
        $customer_stmt->execute([$input['customer_phone']]);
        $existing_customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_customer) {
            $customer_id = $existing_customer['id'];
            
            // Update customer info if provided
            if (!empty($input['customer_name']) || !empty($input['customer_email'])) {
                $update_stmt = $db->prepare("UPDATE customers SET name = COALESCE(?, name), email = COALESCE(?, email) WHERE id = ?");
                $update_stmt->execute([
                    $input['customer_name'] ?: null,
                    $input['customer_email'] ?: null,
                    $customer_id
                ]);
            }
        } else {
            // Create new customer
            $create_customer_stmt = $db->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
            $create_customer_stmt->execute([
                $input['customer_name'],
                $input['customer_phone'],
                $input['customer_email'] ?? null
            ]);
            $customer_id = $db->lastInsertId();
        }
        
        // Insert appointment with pending status
        $appointment_stmt = $db->prepare("
            INSERT INTO appointments (
                customer_id, customer_name, customer_phone, customer_email,
                service_id, service_name, service_duration, service_price,
                specialist_id, specialist_name, start_at, end_at,
                status, notes, source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'public')
        ");
        
        $appointment_stmt->execute([
            $customer_id,
            $input['customer_name'],
            $input['customer_phone'],
            $input['customer_email'] ?? null,
            $input['service_id'],
            $service['title_ar'],
            $duration,
            $service['price'],
            $input['specialist_id'],
            $specialist['name_ar'],
            $start_at->format('Y-m-d H:i:s'),
            $end_at->format('Y-m-d H:i:s'),
            $input['notes'] ?? 'حجز من الموقع الإلكتروني'
        ]);
        
        $appointment_id = $db->lastInsertId();
        
        // Send notification to admin
        sendBookingNotification($appointment_id, 'new_booking');
        
        $db->commit();
        
        // Generate booking reference
        $booking_reference = 'MAI-' . date('Ymd') . '-' . str_pad($appointment_id, 4, '0', STR_PAD_LEFT);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم استلام طلب الحجز بنجاح. سيتم التواصل معك لتأكيد الموعد',
            'booking_reference' => $booking_reference,
            'appointment_id' => $appointment_id,
            'details' => [
                'service' => $service['title_ar'],
                'specialist' => $specialist['name_ar'],
                'date' => $start_at->format('Y-m-d'),
                'time' => $start_at->format('H:i'),
                'duration' => $duration . ' دقيقة',
                'price' => number_format($service['price'], 2) . ' ر.س'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
