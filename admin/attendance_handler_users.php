<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

// --- HELPER FUNCTIONS ---
function get_setting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

// --- MAIN LOGIC ---
try {
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(403);
        throw new Exception('Authentication required.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        http_response_code(400);
        throw new Exception('Invalid request.');
    }

    $user_id = $_SESSION['admin_id'];
    $action = $_POST['action'];
    
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    $stmt_last = $db->prepare("SELECT event_type FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() ORDER BY event_time DESC LIMIT 1");
    $stmt_last->execute([$user_id]);
    $last_event_type = $stmt_last->fetchColumn();

    $message = '';
    $new_status = $action;

    switch ($action) {
        case 'in':
            if ($last_event_type === 'in' || $last_event_type === 'break_start') {
                throw new Exception('You are already checked in.');
            }
            
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;

            if (!$latitude || !$longitude) throw new Exception('Location data is missing.');

            // Geolocation Validation (Optional, based on your settings)
            $work_lat = get_setting($db, 'work_location_lat');
            $work_lng = get_setting($db, 'work_location_lng');
            $work_radius = (float) get_setting($db, 'work_location_radius_meters', 200);

            if ($work_lat && $work_lng) {
                $distance = haversine_distance((float)$work_lat, (float)$work_lng, (float)$latitude, (float)$longitude);
                if ($distance > $work_radius) {
                    throw new Exception('You are not within the allowed work area. Distance: ' . round($distance) . 'm');
                }
            }

            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, latitude, longitude) VALUES (?, 'in', ?, ?)");
            $stmt->execute([$user_id, $latitude, $longitude]);
            $message = 'Checked in successfully.';
            break;

        case 'out':
            if ($last_event_type !== 'in' && $last_event_type !== 'break_end') {
                throw new Exception('You must be checked in to check out.');
            }

            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            if (!$latitude || !$longitude) throw new Exception('Location data is missing.');
            
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, latitude, longitude) VALUES (?, 'out', ?, ?)");
            $stmt->execute([$user_id, $latitude, $longitude]);
            $message = 'Checked out successfully. Your work summary has been recorded.';
            
            // Here you can add the logic to calculate total hours and update the summary table if needed.
            break;

        case 'break_start':
            if (!in_array($last_event_type, ['in', 'break_end'])) {
                throw new Exception('You must be checked in to start a break.');
            }
            $break_id = $_POST['break_rule_id'] ?? null;
            if (empty($break_id)) {
                throw new Exception('Please select a break type.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, break_rule_id) VALUES (?, 'break_start', ?)");
            $stmt->execute([$user_id, $break_id]);
            $message = 'Break started.';
            break;

        case 'break_end':
            if ($last_event_type !== 'break_start') {
                throw new Exception('You are not currently on a break.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type) VALUES (?, 'break_end')");
            $stmt->execute([$user_id]);
            $message = 'Break ended. Welcome back!';
            $new_status = 'in'; // After ending a break, you are checked in.
            break;
            
        default:
            throw new Exception('Invalid action specified.');
    }

    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message, 'new_status' => $new_status]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(422); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;