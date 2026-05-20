<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once '../config/database.php'; // Ensure this path is correct

// --- LANGUAGE & TRANSLATION LOGIC ---
$lang = $_SESSION['lang'] ?? 'ar';
$t = [
    'en' => [
        'checked_in' => 'Successfully checked in.', 'checked_out' => 'Successfully checked out.',
        'already_checked_in' => 'You are already checked in.', 'not_checked_in' => 'You must check in before you can check out.',
        'break_started' => 'Break started.', 'break_ended' => 'Break ended. Welcome back!',
        'must_be_checked_in_for_break' => 'You must be checked in to start a break.',
        'not_on_break' => 'You are not currently on a break.', 'select_break_type' => 'Please select a break type.',
        'db_error' => 'Database error. Could not process request.', 'missing_data' => 'Missing location data. Please enable location and try again.',
        'auth_required' => 'Authentication required.', 'invalid_request' => 'Invalid request.',
    ],
    'ar' => [
        'checked_in' => 'تم تسجيل الحضور بنجاح.', 'checked_out' => 'تم تسجيل الانصراف بنجاح.',
        'already_checked_in' => 'أنت مسجل حضور بالفعل.', 'not_checked_in' => 'يجب عليك تسجيل الحضور أولاً قبل تسجيل الانصراف.',
        'break_started' => 'تم بدء فترة الراحة.', 'break_ended' => 'انتهت فترة الراحة. مرحباً بعودتك!',
        'must_be_checked_in_for_break' => 'يجب أن تكون مسجل حضور لبدء راحة.',
        'not_on_break' => 'أنت لست في فترة راحة حالياً.', 'select_break_type' => 'يرجى اختيار نوع الراحة.',
        'db_error' => 'خطأ في قاعدة البيانات. لا يمكن معالجة الطلب.', 'missing_data' => 'بيانات الموقع غير متوفرة. يرجى تفعيل الموقع والمحاولة مرة أخرى.',
        'auth_required' => 'المصادقة مطلوبة.', 'invalid_request' => 'طلب غير صالح.',
    ]
];

function send_json_error($message, $http_code = 422) {
    http_response_code($http_code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// --- MAIN LOGIC ---
try {
    if (!isset($_SESSION['admin_id'])) { send_json_error($t[$lang]['auth_required'], 403); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) { send_json_error($t[$lang]['invalid_request'], 400); }

    $user_id = $_SESSION['admin_id'];
    $action = $_POST['action'];

    $database = new Database();
    $db = $database->getConnection();

    $stmt_last = $db->prepare("SELECT event_type FROM user_attendance_log WHERE user_id = ? AND DATE(event_time) = CURDATE() ORDER BY event_time DESC LIMIT 1");
    $stmt_last->execute([$user_id]);
    $last_event_type = $stmt_last->fetchColumn();

    $message = '';
    $new_status = $action;

    if ($action === 'in') {
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        if (empty($latitude) || empty($longitude)) { send_json_error($t[$lang]['missing_data']); }
        if ($last_event_type === 'in' || $last_event_type === 'break_start') { send_json_error($t[$lang]['already_checked_in']); }
        
        $insert_stmt = $db->prepare("INSERT INTO user_attendance_log (user_id, event_type, latitude, longitude) VALUES (?, 'in', ?, ?)");
        $insert_stmt->execute([$user_id, $latitude, $longitude]);
        $message = $t[$lang]['checked_in'];

    } elseif ($action === 'out') {
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        if (empty($latitude) || empty($longitude)) { send_json_error($t[$lang]['missing_data']); }
        if ($last_event_type !== 'in' && $last_event_type !== 'break_end') { send_json_error($t[$lang]['not_checked_in']); }
        
        $insert_stmt = $db->prepare("INSERT INTO user_attendance_log (user_id, event_type, latitude, longitude) VALUES (?, 'out', ?, ?)");
        $insert_stmt->execute([$user_id, $latitude, $longitude]);
        $message = $t[$lang]['checked_out'];

    } elseif ($action === 'break_start') {
        $break_id = $_POST['break_rule_id'] ?? null;
        if (empty($break_id)) { send_json_error($t[$lang]['select_break_type']); }
        if (!in_array($last_event_type, ['in', 'break_end'])) { send_json_error($t[$lang]['must_be_checked_in_for_break']); }
        
        $insert_stmt = $db->prepare("INSERT INTO user_attendance_log (user_id, event_type, break_rule_id) VALUES (?, 'break_start', ?)");
        $insert_stmt->execute([$user_id, $break_id]);
        $message = $t[$lang]['break_started'];

    } elseif ($action === 'break_end') {
        if ($last_event_type !== 'break_start') { send_json_error($t[$lang]['not_on_break']); }
        
        $insert_stmt = $db->prepare("INSERT INTO user_attendance_log (user_id, event_type) VALUES (?, 'break_end')");
        $insert_stmt->execute([$user_id]);
        $message = $t[$lang]['break_ended'];
        $new_status = 'in'; // After ending a break, you are checked in.
    
    } else {
        send_json_error($t[$lang]['invalid_request']);
    }

    echo json_encode(['success' => true, 'message' => $message, 'new_status' => $new_status]);

} catch (Exception $e) {
    error_log("Attendance Processing Error: " . $e->getMessage());
    send_json_error($t[$lang]['db_error']);
}