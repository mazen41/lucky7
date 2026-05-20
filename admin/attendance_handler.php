<?php
// ================= START: ERROR DEBUGGING =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ================== END: ERROR DEBUGGING ==================

session_start();
header('Content-Type: application/json');

/**
 * Helper function to get a single setting value from the database.
 */
function get_setting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

/**
 * Calculates the distance between two GPS coordinates in meters using the Haversine formula.
 */
function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
       cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
       sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}


try {
    require_once '../config/database.php';

    // Basic security and setup
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception('Authentication required.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        http_response_code(400);
        throw new Exception('Invalid request method or missing action.');
    }

    $user_id = $_SESSION['user_id'];
    $database = new Database();
    $db = $database->getConnection();
    $action = $_POST['action'];
    $break_id = $_POST['break_rule_id'] ?? null;

    $db->beginTransaction();

    $stmt_last = $db->prepare("SELECT event_type FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() ORDER BY event_time DESC LIMIT 1");
    $stmt_last->execute([$user_id]);
    $last_event_type = $stmt_last->fetchColumn();
    
    if ($action === 'in') {
        $stmt_checkout_check = $db->prepare("SELECT COUNT(*) FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() AND event_type = 'out'");
        $stmt_checkout_check->execute([$user_id]);
        if ($stmt_checkout_check->fetchColumn() > 0) {
            throw new Exception('You have already checked out for the day and cannot check in again.');
        }
    }

    $message = '';
    $new_status = $action;

    switch ($action) {
        case 'in':
            // === NEW: GEOLOCATION VALIDATION LOGIC ===
            $work_lat = get_setting($db, 'work_location_lat');
            $work_lng = get_setting($db, 'work_location_lng');
            $work_radius = (float) get_setting($db, 'work_location_radius_meters', 200);

            // Only perform the check if the location has been configured in the admin panel.
            if (!empty($work_lat) && !empty($work_lng)) {
                if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
                    throw new Exception('Location data was not received. Please enable location services and try again.');
                }
                
                $user_lat = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
                $user_lng = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

                if ($user_lat === false || $user_lng === false) {
                    throw new Exception('Invalid location data received from your device.');
                }
                
                $distance = haversine_distance((float)$work_lat, (float)$work_lng, $user_lat, $user_lng);

                // Check if the calculated distance is greater than the allowed radius.
                if ($distance > $work_radius) {
                    throw new Exception('Check-in failed. You are not within the allowed work area. You are approx. ' . round($distance) . ' meters away.');
                }
            }
            // === END: GEOLOCATION VALIDATION LOGIC ===

            if ($last_event_type === 'in' || $last_event_type === 'break_start') {
                throw new Exception('You are already checked in.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, event_time) VALUES (?, 'in', NOW())");
            $stmt->execute([$user_id]);
            $message = 'Checked in successfully.';
            break;

        case 'out':
            if ($last_event_type !== 'in' && $last_event_type !== 'break_end') {
                throw new Exception('You must be checked in to check out.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, event_time) VALUES (?, 'out', NOW())");
            $stmt->execute([$user_id]);
            $message = 'Checked out successfully.';

            // Calculation and summary logic (remains unchanged)
            $stmt_events = $db->prepare(
                "SELECT event_type, event_time FROM hr_attendance_log 
                 WHERE employee_id = ? AND DATE(event_time) = CURDATE() 
                 ORDER BY event_time ASC"
            );
            $stmt_events->execute([$user_id]);
            $all_events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

            if (count($all_events) > 0) {
                $first_check_in = null;
                $last_check_out = null;
                $total_break_seconds = 0;
                $break_start_time = null;

                foreach ($all_events as $event) {
                    $event_time_obj = new DateTime($event['event_time']);
                    if ($event['event_type'] === 'in' && !$first_check_in) $first_check_in = $event_time_obj;
                    if ($event['event_type'] === 'break_start') $break_start_time = $event_time_obj;
                    if ($event['event_type'] === 'break_end' && $break_start_time) {
                        $diff = $event_time_obj->getTimestamp() - $break_start_time->getTimestamp();
                        $total_break_seconds += $diff;
                        $break_start_time = null;
                    }
                    if ($event['event_type'] === 'out') $last_check_out = $event_time_obj;
                }

                if ($first_check_in && $last_check_out) {
                    $break_minutes = round($total_break_seconds / 60);
                    $net_work_seconds = ($last_check_out->getTimestamp() - $first_check_in->getTimestamp()) - $total_break_seconds;
                    $total_hours_worked = number_format($net_work_seconds / 3600, 2, '.', '');
                    
                    $summary_sql = "
                        INSERT INTO hr_attendance 
                            (employee_id, date, check_in, check_out, break_minutes, total_hours_worked, status)
                        VALUES (?, CURDATE(), ?, ?, ?, ?, 'present')
                        ON DUPLICATE KEY UPDATE
                            check_in = VALUES(check_in), check_out = VALUES(check_out),
                            break_minutes = VALUES(break_minutes), total_hours_worked = VALUES(total_hours_worked),
                            status = VALUES(status)";
                    
                    $stmt_summary = $db->prepare($summary_sql);
                    $stmt_summary->execute([$user_id, $first_check_in->format('H:i:s'), $last_check_out->format('H:i:s'), $break_minutes, $total_hours_worked]);
                }
            }
            break;

        case 'break_start':
            if (!in_array($last_event_type, ['in', 'break_end'])) {
                throw new Exception('You must be checked in to start a break.');
            }
            if (empty($break_id)) {
                throw new Exception('Please select a break type.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, break_rule_id, event_time) VALUES (?, 'break_start', ?, NOW())");
            $stmt->execute([$user_id, $break_id]);
            $message = 'Break started successfully.';
            break;

        case 'break_end':
            if ($last_event_type !== 'break_start') {
                throw new Exception('You are not currently on a break.');
            }
            $stmt = $db->prepare("INSERT INTO hr_attendance_log (employee_id, event_type, event_time) VALUES (?, 'break_end', NOW())");
            $stmt->execute([$user_id]);
            $message = 'Break ended successfully.';
            $new_status = 'in';
            break;
            
        default:
            throw new Exception('Invalid action.');
    }

    $db->commit();
    
    $check_in_time_val = null;
    if ($new_status !== 'out') {
        $stmt_checkin = $db->prepare("SELECT event_time FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() AND event_type = 'in' ORDER BY event_time ASC LIMIT 1");
        $stmt_checkin->execute([$user_id]);
        $check_in_time_val = $stmt_checkin->fetchColumn();
    }

    $response = [
        'success' => true, 
        'message' => $message,
        'new_status' => $new_status,
        'check_in_time' => $check_in_time_val ? date('h:i A', strtotime($check_in_time_val)) : null
    ];
    echo json_encode($response);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(422); // Use 422 for failed validation
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;