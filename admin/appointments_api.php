<?php
/**
 * Appointments API - Comprehensive Endpoint Handler (Corrected Version)
 */

require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Authentication required']); exit;
}

$admin = ['id' => $_SESSION['admin_id'] ?? 1];

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

// Helper function to check for appointment conflicts
function checkSpecialistAvailability($db, $specialist_id, $start_at, $end_at, $exclude_appointment_id = null) {
    $query = "SELECT COUNT(*) FROM appointments 
              WHERE specialist_id = ? 
              AND status NOT IN ('cancelled', 'no_show')
              AND (
                  (start_at < ? AND end_at > ?) OR -- Overlaps the start
                  (start_at >= ? AND start_at < ?)  -- Starts within the slot
              )";
    
    $params = [$specialist_id, $end_at, $start_at, $start_at, $end_at];
    
    if ($exclude_appointment_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_appointment_id;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // --- GET REQUESTS ---
    if ($method === 'GET') {
        // Handle customer search
        if ($action === 'search_customers') {
            $term = $_GET['term'] ?? '';
            if (strlen($term) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $stmt = $db->prepare("SELECT id, name_ar, phone FROM customers WHERE name_ar LIKE ? OR phone LIKE ? LIMIT 10");
            $stmt->execute(["%$term%", "%$term%"]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // Handle fetching appointments for the calendar
        $query = "SELECT a.* FROM appointments a WHERE 1=1";
        $params = [];
        if (!empty($_GET['specialist'])) {
            $query .= " AND a.specialist_id = ?";
            $params[] = $_GET['specialist'];
        }
        if (!empty($_GET['status'])) {
            $query .= " AND a.status = ?";
            $params[] = $_GET['status'];
        }
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $calendarEvents = [];
        foreach ($appointments as $apt) {
            $calendarEvents[] = [
                'id' => $apt['id'],
                'title' => ($apt['customer_name'] ?? 'عميل') . ' - ' . ($apt['service_name'] ?? 'خدمة'),
                'start' => $apt['start_at'],
                'end' => $apt['end_at'],
                'extendedProps' => $apt
            ];
        }
        echo json_encode(['success' => true, 'data' => $calendarEvents]);
    }

    // --- POST REQUESTS (CREATE NEW) ---
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) throw new Exception('بيانات غير صحيحة');

        $required = ['customer_name', 'customer_phone', 'service_id', 'specialist_id', 'start_at'];
        foreach ($required as $field) if (empty($input[$field])) throw new Exception("الحقل {$field} مطلوب");

        // CORRECTED: Validate that the appointment date is not in the past
        $riyadh_tz = new DateTimeZone('Asia/Riyadh');
        $start_at_dt = new DateTime($input['start_at'], $riyadh_tz);
        $tomorrow = new DateTime('tomorrow', $riyadh_tz);
        if ($start_at_dt < $tomorrow) {
            throw new Exception('لا يمكن حجز موعد إلا من اليوم التالي فصاعدًا.');
        }

        // Get service details to calculate end time
        $service_stmt = $db->prepare("SELECT name_ar, price, service_duration FROM services WHERE id = ?");
        $service_stmt->execute([$input['service_id']]);
        $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$service) throw new Exception('الخدمة غير موجودة');

        $duration = $service['service_duration'] ?? 60;
        $end_at_dt = clone $start_at_dt;
        $end_at_dt->add(new DateInterval('PT' . $duration . 'M'));
        $start_at_sql = $start_at_dt->format('Y-m-d H:i:s');
        $end_at_sql = $end_at_dt->format('Y-m-d H:i:s');

        // CORRECTED: Robust conflict check
        if (!checkSpecialistAvailability($db, $input['specialist_id'], $start_at_sql, $end_at_sql)) {
            throw new Exception('الأخصائي غير متاح في هذا الوقت. يوجد موعد آخر يتعارض معه.');
        }
        
        // CORRECTED: Auto-create customer if they don't exist
        $customer_id = $input['customer_id'] ?? null;
        if (!$customer_id) {
            $cust_stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
            $cust_stmt->execute([$input['customer_phone']]);
            $existing_customer_id = $cust_stmt->fetchColumn();
            if ($existing_customer_id) {
                $customer_id = $existing_customer_id;
            } else {
                $create_cust_stmt = $db->prepare("INSERT INTO customers (name_ar, phone, status, created_by) VALUES (?, ?, 'active', ?)");
                $create_cust_stmt->execute([$input['customer_name'], $input['customer_phone'], $admin['id']]);
                $customer_id = $db->lastInsertId();
            }
        }
        
        $specialist_stmt = $db->prepare("SELECT name_ar FROM team_members WHERE id = ?");
        $specialist_stmt->execute([$input['specialist_id']]);
        $specialist_name = $specialist_stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO appointments (customer_id, customer_name, customer_phone, service_id, service_name, service_duration, service_price, specialist_id, specialist_name, start_at, end_at, notes, created_by, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'internal')");
        $stmt->execute([ $customer_id, $input['customer_name'], $input['customer_phone'], $input['service_id'], $service['name_ar'], $duration, $service['price'], $input['specialist_id'], $specialist_name, $start_at_sql, $end_at_sql, $input['notes'] ?? null, $admin['id'] ]);
        
        echo json_encode(['success' => true, 'message' => 'تم إنشاء الموعد بنجاح']);
    }

    // --- PATCH REQUESTS (UPDATE EXISTING) ---
    elseif ($method === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) throw new Exception('معرف الموعد مطلوب للتحديث');
        
        $appointment_id = $input['id'];
        
        // Fetch current appointment to get its duration and specialist
        $current_stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
        $current_stmt->execute([$appointment_id]);
        $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new Exception('الموعد غير موجود');

        $update_fields = [];
        $params = [];
        
        // Handle time change (drag & drop or edit)
        if (isset($input['start_at'])) {
            $riyadh_tz = new DateTimeZone('Asia/Riyadh');
            $start_at_dt = new DateTime($input['start_at'], $riyadh_tz);
            $tomorrow = new DateTime('tomorrow', $riyadh_tz);
            if ($start_at_dt < $tomorrow) throw new Exception('لا يمكن نقل موعد إلى وقت سابق للغد.');

            $duration = $current['service_duration'];
            $end_at_dt = clone $start_at_dt;
            $end_at_dt->add(new DateInterval('PT' . $duration . 'M'));
            $start_at_sql = $start_at_dt->format('Y-m-d H:i:s');
            $end_at_sql = $end_at_dt->format('Y-m-d H:i:s');
            
            if (!checkSpecialistAvailability($db, $current['specialist_id'], $start_at_sql, $end_at_sql, $appointment_id)) {
                throw new Exception('الوقت الجديد يتعارض مع موعد آخر للأخصائي.');
            }
            $update_fields[] = "start_at = ?"; $params[] = $start_at_sql;
            $update_fields[] = "end_at = ?"; $params[] = $end_at_sql;
        }

        // Handle status change
        if (isset($input['status'])) {
            $update_fields[] = "status = ?"; $params[] = $input['status'];
        }

        // You can add more editable fields here if needed
        
        if (empty($update_fields)) {
            echo json_encode(['success' => true, 'message' => 'لا توجد بيانات للتحديث']);
            exit;
        }
        
        $query = "UPDATE appointments SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $params[] = $appointment_id;
        
        $db->prepare($query)->execute($params);
        echo json_encode(['success' => true, 'message' => 'تم تحديث الموعد بنجاح']);
    }
    
    else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(400); // Bad Request for logical errors
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>