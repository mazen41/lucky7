<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

// Ensure the user is an authenticated admin
checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

// --- HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        // Handle days off (capture array from checkboxes)
        // If no days are selected, default to an empty array
        $days_off = isset($_POST['days_off']) ? $_POST['days_off'] : [];
        $days_off_json = json_encode($days_off);

        $query = "INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)
                  ON DUPLICATE KEY UPDATE setting_value = :value";
        
        $stmt = $db->prepare($query);

        // 1. Update start time
        $stmt->execute([
            'key' => 'appointment_working_hours_start', 
            'value' => $start_time
        ]);
        
        // 2. Update end time
        $stmt->execute([
            'key' => 'appointment_working_hours_end', 
            'value' => $end_time
        ]);

        // 3. Update days off
        $stmt->execute([
            'key' => 'appointment_days_off', 
            'value' => $days_off_json
        ]);

        $_SESSION['message'] = 'تم تحديث أوقات الدوام وأيام العطلة بنجاح.';

    } catch (Exception $e) {
        $_SESSION['error'] = 'حدث خطأ أثناء تحديث الإعدادات: ' . $e->getMessage();
    }

    header("Location: working_hours_settings.php");
    exit;
}

// --- FETCH CURRENT SETTINGS FOR DISPLAY ---
try {
    // Fetch start time, end time, and days off
    $settings_stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('appointment_working_hours_start', 'appointment_working_hours_end', 'appointment_days_off')");
    $settings_stmt->execute();
    $settings_data = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $working_hours_start = $settings_data['appointment_working_hours_start'] ?? '09:00';
    $working_hours_end = $settings_data['appointment_working_hours_end'] ?? '17:00';
    
    // Decode the JSON string back into an array. Default to empty array if not set.
    $saved_days_off = isset($settings_data['appointment_days_off']) ? json_decode($settings_data['appointment_days_off'], true) : [];
    if (!is_array($saved_days_off)) {
        $saved_days_off = [];
    }

} catch (Exception $e) {
    $working_hours_start = '09:00';
    $working_hours_end = '17:00';
    $saved_days_off = [];
    $_SESSION['error_on_load'] = "خطأ في جلب الإعدادات الحالية: " . $e->getMessage();
}

// Define Days of the Week for the UI
$week_days = [
    'Saturday'  => 'السبت',
    'Sunday'    => 'الأحد',
    'Monday'    => 'الاثنين',
    'Tuesday'   => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday'  => 'الخميس',
    'Friday'    => 'الجمعة',
];

// --- PAGE SETUP ---
$page_title = 'إعدادات أوقات الدوام';
$page_subtitle = 'تحديد ساعات العمل الرسمية وأيام العطلات';
ob_start();
?>

<!-- Back Button -->
<div class="flex justify-end mb-4">
    <a href="settings.php" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg shadow-sm transition">
        <i class="fas fa-arrow-right text-mai-brown"></i>
        <span>العودة إلى الإعدادات</span>
    </a>
</div>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center gap-3">
        <div>
            <h1 class="text-lg font-bold text-mai-dark-brown"><?= $page_title ?></h1>
            <p class="text-xs text-gray-500"><?= $page_subtitle ?></p>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div class="p-6">

    <!-- Display Feedback Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?= $_SESSION['message'] ?></p>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error']) || isset($_SESSION['error_on_load'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?= $_SESSION['error'] ?? $_SESSION['error_on_load'] ?></p>
        </div>
        <?php unset($_SESSION['error']); unset($_SESSION['error_on_load']); ?>
    <?php endif; ?>


    <!-- Settings Form Card -->
    <div class="bg-white rounded-xl shadow-lg max-w-3xl mx-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-bold text-mai-dark-brown">تعديل أوقات العمل والعطلات</h2>
        </div>
        <form method="POST" class="p-8">
            <div class="space-y-8">
                
                <!-- Working Hours Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Start Time Input -->
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-800 mb-2">وقت بدء الدوام</label>
                        <input type="time" id="start_time" name="start_time" 
                               value="<?= htmlspecialchars($working_hours_start) ?>"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-mai-brown focus:border-mai-brown p-3 text-lg" 
                               required>
                    </div>

                    <!-- End Time Input -->
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-800 mb-2">وقت انتهاء الدوام</label>
                        <input type="time" id="end_time" name="end_time"
                               value="<?= htmlspecialchars($working_hours_end) ?>"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-mai-brown focus:border-mai-brown p-3 text-lg" 
                               required>
                    </div>
                </div>

                <hr class="border-gray-200">

                <!-- Days Off Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-800 mb-4">
                        أيام العطلة الأسبوعية 
                        <span class="text-xs font-normal text-gray-500">(حدد الأيام التي لا يوجد فيها عمل)</span>
                    </label>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($week_days as $english_day => $arabic_day): ?>
                            <?php 
                                // Check if this day is in the saved array
                                $is_checked = in_array($english_day, $saved_days_off) ? 'checked' : '';
                            ?>
                            <label class="relative flex items-center p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" 
                                       name="days_off[]" 
                                       value="<?= $english_day ?>" 
                                       <?= $is_checked ?>
                                       class="h-5 w-5 text-mai-brown border-gray-300 rounded focus:ring-mai-brown">
                                <span class="mr-3 text-gray-700 font-medium select-none"><?= $arabic_day ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-5 border-t border-gray-200 flex justify-end">
                <button type="submit" class="bg-mai-brown text-white px-8 py-3 rounded-lg hover:bg-mai-dark-brown focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-mai-brown font-semibold transition shadow-md">
                    <i class="fas fa-save ml-2"></i>
                    حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>

</div>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>