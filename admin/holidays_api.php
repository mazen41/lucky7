<?php
/**
 * API for Managing Holidays and Weekly Off Days
 */

// Essential Setup
require_once '../config/database.php'; // Adjust path if necessary

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure an admin is logged in (uncomment if you have this)
/*
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}
*/

// Set the response content type to JSON
header('Content-Type: application/json');

// Get the database connection
$database = new Database();
$db = $database->getConnection();

// Determine the requested action
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // --- ACTION: Get All Holidays and Weekly Offs ---
        case 'get_all':
            // Fetch holidays
            $holidaysStmt = $db->query("SELECT id, name, date, is_recurring FROM holidays ORDER BY date ASC");
            $holidays = $holidaysStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch weekly off days from the settings table
            $settingsStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $settingsStmt->execute(['weekly_days_off']);
            $weekly_off_days = $settingsStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'holidays' => $holidays,
                'weekly_off_days' => $weekly_off_days ?? '' // Return empty string if not set
            ]);
            break;

        // --- ACTION: Add a New Holiday ---
        case 'add':
            // **FIX:** Read data from $_POST because the frontend is sending FormData
            if (!isset($_POST['name']) || empty($_POST['name']) || !isset($_POST['date']) || empty($_POST['date'])) {
                throw new Exception('اسم العطلة والتاريخ حقول مطلوبة.');
            }

            $name = htmlspecialchars(strip_tags($_POST['name']));
            $date = $_POST['date'];
            // Checkbox value will be '1' or '0'
            $is_recurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] === '1' ? 1 : 0;
            
            // Calculate day_of_week based on your table structure (0=Sunday, 6=Saturday)
            $day_of_week = date('w', strtotime($date));

            // Prepare the SQL statement for YOUR table structure
            $stmt = $db->prepare(
                "INSERT INTO holidays (name, date, day_of_week, is_recurring) VALUES (?, ?, ?, ?)"
            );

            if ($stmt->execute([$name, $date, $day_of_week, $is_recurring])) {
                echo json_encode(['success' => true, 'message' => 'تمت إضافة العطلة بنجاح.']);
            } else {
                throw new Exception('فشل في حفظ العطلة في قاعدة البيانات.');
            }
            break;

        // --- ACTION: Delete a Holiday ---
        case 'delete':
            // Data is sent via FormData, so use $_POST
            if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                throw new Exception('معرف العطلة غير صالح.');
            }
            $id = $_POST['id'];

            $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
            if ($stmt->execute([$id])) {
                echo json_encode(['success' => true, 'message' => 'تم حذف العطلة.']);
            } else {
                throw new Exception('فشل في حذف العطلة.');
            }
            break;

        // --- ACTION: Update Weekly Off Days ---
        case 'update_weekly_offs':
            // Data is sent via FormData, so use $_POST
            // The 'days' value will be a comma-separated string, e.g., "5,6"
            $days_string = $_POST['days'] ?? '';

            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both cases (setting exists or not)
            // This requires 'setting_key' to be a UNIQUE index in your site_settings table.
            $stmt = $db->prepare(
                "INSERT INTO site_settings (setting_key, setting_value) 
                 VALUES ('weekly_days_off', ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?"
            );

            if ($stmt->execute([$days_string, $days_string])) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث أيام الإجازة الأسبوعية بنجاح.']);
            } else {
                throw new Exception('فشل في حفظ أيام الإجازة.');
            }
            break;

        default:
            throw new Exception('الإجراء المطلوب غير معروف.');
    }
} catch (Exception $e) {
    // Catch any errors and return a proper JSON error response
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>