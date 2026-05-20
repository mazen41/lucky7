<?php
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
ini_set('display_errors', 1); 
error_reporting(E_ALL);

require_once 'config/database.php';

function validateSaPhone($phone) {
    $cleaned_phone = preg_replace('/\D/', '', $phone);
    // Ensures phone starts with 05 and is 10 digits long
    if (preg_match('/^05\d{8}$/', $cleaned_phone)) {
        return $cleaned_phone;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit();
}

$db = null; 
try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception('فشل في الاتصال بقاعدة البيانات');
    
    $db->beginTransaction();

    // --- INPUT GATHERING & VALIDATION ---
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $specialist_id = filter_input(INPUT_POST, 'specialist_id', FILTER_VALIDATE_INT);
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_time = trim($_POST['booking_time'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone_raw = trim($_POST['customer_phone'] ?? '');
    $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    
    if (empty($service_id) || empty($specialist_id) || empty($booking_date) || empty($booking_time) || empty($customer_name) || empty($customer_phone_raw)) {
        throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
    }

    $customer_phone_for_db = validateSaPhone($customer_phone_raw);
    if (!$customer_phone_for_db) {
        throw new Exception('رقم الجوال غير صحيح. يجب أن يبدأ بـ 05 ويتكون من 10 أرقام');
    }

    $start_datetime = new DateTime($booking_date . ' ' . $booking_time);
    $day_of_week_english = $start_datetime->format('l'); // e.g., "Friday"
    $day_of_week_name = strtolower($day_of_week_english);

    // --- 1. CHECK FOR PAST DATES ---
    $tomorrow_obj = new DateTime('tomorrow', new DateTimeZone('UTC'));
    $tomorrow_obj->setTime(0, 0, 0);
    if ($start_datetime < $tomorrow_obj) {
        throw new Exception('لا يمكن الحجز في نفس اليوم أو في الماضي.');
    }

    // --- 2. CHECK FOR DAYS OFF (NEW LOGIC) ---
    // Fetch days off from site_settings
    $settings_days_stmt = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'appointment_days_off'");
    $days_off_json = $settings_days_stmt->fetchColumn();
    $days_off_array = $days_off_json ? json_decode($days_off_json, true) : [];
    
    // Check if the selected day is in the days off array
    if (is_array($days_off_array)) {
        // Case-insensitive check (e.g. check if "Friday" exists in list)
        $days_off_array_lower = array_map('strtolower', $days_off_array);
        if (in_array($day_of_week_name, $days_off_array_lower)) {
            throw new Exception("عذراً، يوم " . $start_datetime->format('l') . " هو يوم عطلة رسمية للصالون.");
        }
    }

    // --- DATA VERIFICATION FROM DATABASE ---
    $settings_stmt = $db->query("SELECT default_buffer FROM booking_settings WHERE id = 1");
    $default_buffer = $settings_stmt->fetchColumn() ?? 10;
    
    $service_stmt = $db->prepare("SELECT name_ar, duration, buffer_time, price, min_price FROM services WHERE id = ? AND is_active = 1 AND is_bookable = 1");
    $service_stmt->execute([$service_id]);
    $db_service = $service_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$db_service) throw new Exception('الخدمة المحددة غير متوفرة');
    
    $service_price_from_db = $db_service['min_price'] ?? $db_service['price'];
    
    $service_name_from_db = $db_service['name_ar'];
    $duration = $db_service['duration'];
    $buffer = $db_service['buffer_time'] ?? $default_buffer;

    if (abs($total_amount - (float)$service_price_from_db) > 0.01) {
        throw new Exception('The provided total amount does not match the service base price.');
    }

    $specialist_stmt = $db->prepare("SELECT name_ar FROM team_members WHERE id = ? AND is_active = 1 AND is_specialist = 1");
    $specialist_stmt->execute([$specialist_id]);
    $specialist_name_from_db = $specialist_stmt->fetchColumn();
    if (!$specialist_name_from_db) throw new Exception('الأخصائية المحددة غير متوفرة');

    $end_datetime = (clone $start_datetime)->modify("+{$duration} minutes");

    // --- FINAL SERVER-SIDE CONFLICT & AVAILABILITY CHECK ---
    $working_hours = null;
    $spec_hours_stmt = $db->prepare("SELECT open_time, close_time, is_working FROM specialist_working_hours WHERE specialist_id = ? AND day_of_week = ?");
    $spec_hours_stmt->execute([$specialist_id, $day_of_week_name]);
    $specialist_schedule = $spec_hours_stmt->fetch(PDO::FETCH_ASSOC);

    if ($specialist_schedule && $specialist_schedule['is_working'] == 1 && $specialist_schedule['open_time']) {
        $working_hours = ['open' => $specialist_schedule['open_time'], 'close' => $specialist_schedule['close_time']];
    } else if (!$specialist_schedule) {
        // Fallback to general working hours settings (start/end) from site_settings
        $gen_hours_stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('appointment_working_hours_start', 'appointment_working_hours_end')");
        $gen_settings = $gen_hours_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $gen_start = $gen_settings['appointment_working_hours_start'] ?? '09:00';
        $gen_end = $gen_settings['appointment_working_hours_end'] ?? '17:00';

        $working_hours = ['open' => $gen_start, 'close' => $gen_end];
    }
    
    if (!$working_hours) { throw new Exception('لا توجد ساعات عمل محددة لهذا اليوم.'); }
    
    $day_open_time = new DateTime($booking_date . ' ' . $working_hours['open']);
    $day_close_time = new DateTime($booking_date . ' ' . $working_hours['close']);

    if ($start_datetime < $day_open_time || $end_datetime > $day_close_time) {
        throw new Exception('الوقت المحدد خارج ساعات العمل الرسمية.');
    }

    $end_with_buffer = (clone $end_datetime)->modify("+{$buffer} minutes");
    $appt_stmt = $db->prepare("SELECT a.start_at, a.end_at, COALESCE(s.buffer_time, ?) as buffer FROM appointments a LEFT JOIN services s ON a.service_id = s.id WHERE a.specialist_id = ? AND DATE(a.start_at) = ? AND a.status IN ('pending', 'confirmed')");
    $appt_stmt->execute([$default_buffer, $specialist_id, $booking_date]);
    $existing_appts = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($existing_appts as $appt) {
        $existing_start = new DateTime($appt['start_at']);
        $existing_end_with_buffer = (new DateTime($appt['end_at']))->modify("+{$appt['buffer']} minutes");
        if ($start_datetime < $existing_end_with_buffer && $end_with_buffer > $existing_start) {
            throw new Exception('عذراً، هذا الوقت لم يعد متاحاً. يرجى اختيار وقت آخر.');
        }
    }
    
    // --- CUSTOMER & APPOINTMENT CREATION ---
    $customer_stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
    $customer_stmt->execute([$customer_phone_for_db]);
    $customer_id = $customer_stmt->fetchColumn();

    if (!$customer_id) {
        $customer_code = 'CUS' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $qr_token = uniqid('cus_', true);
        
        $insert_customer_stmt = $db->prepare("INSERT INTO customers (name_ar, phone, customer_code, qr_token, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        $insert_customer_stmt->execute([$customer_name, $customer_phone_for_db, $customer_code, $qr_token]);
        $customer_id = $db->lastInsertId();
    } else {
        $update_customer_stmt = $db->prepare("UPDATE customers SET name_ar = ? WHERE id = ?");
        $update_customer_stmt->execute([$customer_name, $customer_id]);
    }

    $appointment_query = "INSERT INTO appointments 
        (customer_id, customer_name, customer_phone, service_id, service_name, service_price, service_duration, specialist_id, specialist_name, start_at, end_at, total_amount, status, source) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'public')";
    
    $appointment_stmt = $db->prepare($appointment_query);
    
    $appointment_stmt->execute([
        $customer_id, $customer_name, $customer_phone_for_db, $service_id, 
        $service_name_from_db, $service_price_from_db, $duration, $specialist_id, 
        $specialist_name_from_db, $start_datetime->format('Y-m-d H:i:s'), 
        $end_datetime->format('Y-m-d H:i:s'), $total_amount
    ]);
    $appointment_id = $db->lastInsertId();

    $booking_reference = 'MAI' . date('Ymd') . sprintf('%04d', $appointment_id);
    
    $update_ref_stmt = $db->prepare("UPDATE appointments SET booking_reference = ? WHERE id = ?");
    $update_ref_stmt->execute([$booking_reference, $appointment_id]);

    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'تم حجز موعدك بنجاح!', 'booking_data' => ['id' => $appointment_id, 'reference' => $booking_reference]]);
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(400);
    error_log("Booking API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'حدث خطأ أثناء معالجة الطلب.']);
}
?>