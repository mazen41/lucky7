<?php
// Set headers at the very top
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

function handle_shutdown() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'A critical server error occurred.']);
    }
}
register_shutdown_function('handle_shutdown');
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_services_by_category':
            handle_get_services_by_category($db);
            break;
        case 'get_specialists_for_service':
            handle_get_specialists_for_service($db);
            break;
        case 'get_categories': 
            handle_get_categories($db);
            break;
        case 'get_services': 
            handle_get_services($db);
            break;
        case 'get_available_slots':
            handle_get_available_slots($db);
            break;
        case 'get_disabled_dates':
            handle_get_disabled_dates($db);
            break;
        case 'search_customers':
            handle_search_customers($db);
            break;
        case 'find_customer':
            handle_find_customer($db);
            break;
        default:
            throw new InvalidArgumentException('Invalid action specified.');
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database operation failed.']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("General API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred on the server.']);
} finally {
    ob_end_flush();
}

// --- ACTION HANDLERS ---

function handle_get_services_by_category($db) {
    $category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    if (!$category_id) throw new InvalidArgumentException('Invalid Category ID provided.');

    $query = "SELECT id, category_id, name_ar, title_ar, price, min_price, max_price, duration 
              FROM services 
              WHERE category_id = ? AND is_active = 1 AND is_bookable = 1 
              ORDER BY sort_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$category_id]);
    echo json_encode(['success' => true, 'services' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handle_get_specialists_for_service($db) {
    $service_id = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);
    if (!$service_id) throw new InvalidArgumentException('Invalid Service ID provided.');

    $query = "SELECT tm.id, tm.name_ar 
              FROM team_members tm
              JOIN specialist_services ss ON tm.id = ss.specialist_id
              WHERE ss.service_id = ? AND tm.is_active = 1 AND tm.is_specialist = 1
              ORDER BY tm.sort_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$service_id]);
    echo json_encode(['success' => true, 'specialists' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handle_search_customers($db) {
    $query = trim($_GET['query'] ?? '');
    if (mb_strlen($query) < 2) {
        echo json_encode(['success' => true, 'customers' => []]);
        return;
    }
    $search_term = "%{$query}%";
    $sql = "SELECT id, name_ar, phone FROM customers WHERE name_ar LIKE ? OR phone LIKE ? LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([$search_term, $search_term]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'customers' => $customers]);
}

function handle_get_categories($db) { 
    $specialist_id = filter_input(INPUT_GET, 'specialist_id', FILTER_VALIDATE_INT);
    if (!$specialist_id) throw new InvalidArgumentException('Invalid Specialist ID provided.');
    $query = "SELECT DISTINCT c.id, c.name_ar FROM service_categories c JOIN services s ON c.id = s.category_id JOIN specialist_services ss ON s.id = ss.service_id WHERE ss.specialist_id = ? AND c.is_active = 1 AND s.is_active = 1 AND s.is_bookable = 1 ORDER BY c.sort_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$specialist_id]);
    echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handle_get_services($db) { 
    $specialist_id = filter_input(INPUT_GET, 'specialist_id', FILTER_VALIDATE_INT);
    if (!$specialist_id) throw new InvalidArgumentException('Invalid Specialist ID provided.');
    $query = "SELECT s.id, s.category_id, s.name_ar, s.title_ar, s.price, s.min_price, s.max_price, s.duration FROM services s JOIN specialist_services ss ON s.id = ss.service_id WHERE ss.specialist_id = ? AND s.is_active = 1 AND s.is_bookable = 1 ORDER BY s.sort_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$specialist_id]);
    echo json_encode(['success' => true, 'services' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handle_find_customer($db) {
    $phone = preg_replace('/\D/', '', $_GET['phone'] ?? '');
    if (strlen($phone) < 9) { echo json_encode(['success' => false, 'message' => 'Invalid phone.']); return; }
    $stmt = $db->prepare("SELECT id, name_ar FROM customers WHERE phone LIKE ? LIMIT 1");
    $stmt->execute(['%' . $phone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($customer ? ['success' => true, 'customer' => $customer] : ['success' => false, 'message' => 'Customer not found.']);
}

function handle_get_available_slots($db) {
    $date = $_GET['date'] ?? '';
    $specialist_id = filter_input(INPUT_GET, 'specialist_id', FILTER_VALIDATE_INT);
    $service_id = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);

    if (!$date || !$specialist_id || !$service_id) {
        throw new InvalidArgumentException('Missing required parameters for getting slots.');
    }

    $booking_date = new DateTime($date);
    $day_of_week_name = strtolower($booking_date->format('l'));

    // --- CHECK DAYS OFF HERE AS WELL (To return empty slots if manual API call) ---
    $settings_days_stmt = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'appointment_days_off'");
    $days_off_json = $settings_days_stmt->fetchColumn();
    $days_off_array = $days_off_json ? json_decode($days_off_json, true) : [];
    if (is_array($days_off_array)) {
        $days_off_array_lower = array_map('strtolower', $days_off_array);
        if (in_array($day_of_week_name, $days_off_array_lower)) {
            echo json_encode(['success' => true, 'slots' => []]); 
            return;
        }
    }
    // -------------------------------------------------------------------------------

    $settings_stmt = $db->query("SELECT slot_interval, default_buffer FROM booking_settings WHERE id = 1 LIMIT 1");
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
    $slot_interval = $settings['slot_interval'] ?? 30;
    $default_buffer = $settings['default_buffer'] ?? 10;

    $service_stmt = $db->prepare("SELECT duration, buffer_time FROM services WHERE id = ?");
    $service_stmt->execute([$service_id]);
    $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) throw new Exception("Service not found.");
    $service_duration = (int)$service['duration'];
    $service_buffer = $service['buffer_time'] ?? $default_buffer;

    $working_hours = null;
    $spec_hours_stmt = $db->prepare("SELECT open_time, close_time, is_working FROM specialist_working_hours WHERE specialist_id = ? AND day_of_week = ?");
    $spec_hours_stmt->execute([$specialist_id, $day_of_week_name]);
    $specialist_schedule = $spec_hours_stmt->fetch(PDO::FETCH_ASSOC);

    if ($specialist_schedule && $specialist_schedule['is_working'] == 1 && $specialist_schedule['open_time']) {
        $working_hours = ['open' => $specialist_schedule['open_time'], 'close' => $specialist_schedule['close_time']];
    } else if (!$specialist_schedule) {
        // Fallback to General Settings from site_settings
        $gen_hours_stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('appointment_working_hours_start', 'appointment_working_hours_end')");
        $gen_settings = $gen_hours_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $gen_start = $gen_settings['appointment_working_hours_start'] ?? '09:00';
        $gen_end = $gen_settings['appointment_working_hours_end'] ?? '17:00';

        $working_hours = ['open' => $gen_start, 'close' => $gen_end];
    }
    
    if (!$working_hours) { echo json_encode(['success' => true, 'slots' => []]); return; }
    
    $appt_stmt = $db->prepare("SELECT a.start_at, a.end_at, COALESCE(s.buffer_time, ?) as buffer FROM appointments a LEFT JOIN services s ON a.service_id = s.id WHERE a.specialist_id = ? AND DATE(a.start_at) = ? AND a.status IN ('pending', 'confirmed')");
    $appt_stmt->execute([$default_buffer, $specialist_id, $date]);
    $existing_appointments = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);

    $busy_slots = [];
    foreach ($existing_appointments as $appt) {
        $busy_slots[] = ['start' => new DateTime($appt['start_at']), 'end' => (new DateTime($appt['end_at']))->modify("+{$appt['buffer']} minutes")];
    }
    
    $available_slots = [];
    $start_of_day = new DateTime($date . ' ' . $working_hours['open']);
    $end_of_day = new DateTime($date . ' ' . $working_hours['close']);
    $current_slot_start = clone $start_of_day;
    $now = new DateTime();

    while ($current_slot_start < $end_of_day) {
        $potential_slot_end = (clone $current_slot_start)->modify("+{$service_duration} minutes");
        if ($potential_slot_end > $end_of_day || $current_slot_start < $now) {
            $current_slot_start->modify("+{$slot_interval} minutes");
            continue;
        }

        $is_conflicting = false;
        $new_booking_window_end = (clone $potential_slot_end)->modify("+{$service_buffer} minutes");
        foreach ($busy_slots as $busy_slot) {
            if ($current_slot_start < $busy_slot['end'] && $new_booking_window_end > $busy_slot['start']) {
                $is_conflicting = true;
                break;
            }
        }
        if (!$is_conflicting) {
            $available_slots[] = ['value' => $current_slot_start->format('H:i:s'), 'label' => $current_slot_start->format('h:i A')];
        }
        $current_slot_start->modify("+{$slot_interval} minutes");
    }
    echo json_encode(['success' => true, 'slots' => $available_slots]);
}

// --- UPDATED FUNCTION FOR DAYS OFF ---
function handle_get_disabled_dates($db) {
    // 1. Get weekly off days from SITE_SETTINGS (The JSON list we saved)
    $stmt = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'appointment_days_off'");
    $days_off_json = $stmt->fetchColumn();
    $days_off_names = $days_off_json ? json_decode($days_off_json, true) : [];

    // Map English day names to JavaScript getDay() integers (Sunday=0, Monday=1, etc.)
    $day_map = [
        'Sunday' => 0, 'sunday' => 0,
        'Monday' => 1, 'monday' => 1,
        'Tuesday' => 2, 'tuesday' => 2,
        'Wednesday' => 3, 'wednesday' => 3,
        'Thursday' => 4, 'thursday' => 4,
        'Friday' => 5, 'friday' => 5,
        'Saturday' => 6, 'saturday' => 6
    ];

    $weeklyOffDays = [];
    if (is_array($days_off_names)) {
        foreach($days_off_names as $name) {
            // Trim and match
            $clean_name = trim($name);
            if (isset($day_map[$clean_name])) {
                $weeklyOffDays[] = $day_map[$clean_name];
            }
        }
    }
    // Remove duplicates
    $weeklyOffDays = array_unique($weeklyOffDays);

    // 2. Fetch all holidays from the database (Existing Logic)
    $current_year = date('Y');
    $next_year = $current_year + 1;
    
    $non_recurring_q = $db->query("SELECT date FROM holidays WHERE is_recurring = 0");
    $holidays = $non_recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $recurring_q = $db->query("SELECT DATE_FORMAT(date, '%m-%d') AS month_day FROM holidays WHERE is_recurring = 1");
    $recurring_month_days = $recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);

    foreach ($recurring_month_days as $md) {
        $holidays[] = $current_year . '-' . $md;
        $holidays[] = $next_year . '-' . $md;
    }

    $holidays = array_values(array_unique($holidays));

    echo json_encode(['success' => true, 'holidays' => $holidays, 'weeklyOffDays' => array_values($weeklyOffDays)]);
}
?>