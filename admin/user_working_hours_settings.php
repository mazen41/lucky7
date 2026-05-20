<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();
$admin = getCurrentAdmin();

// --- PERMISSION CHECK ---
// Managing employee working hours is a core HR function.
requirePermission('hr.employees.manage');

$database = new Database();
$db = $database->getConnection();

$selected_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// --- HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $working_hours_data = $_POST['working_hours'] ?? [];

    if ($user_id_to_update) {
        try {
            $db->beginTransaction();

            // 1. Delete the old schedule for the user to ensure a clean update
            $delete_stmt = $db->prepare("DELETE FROM user_working_hours WHERE user_id = ?");
            $delete_stmt->execute([$user_id_to_update]);

            // 2. Insert the new schedule
            $insert_stmt = $db->prepare(
                "INSERT INTO user_working_hours (user_id, day_of_week, open_time, close_time, is_working) 
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($working_hours_data as $day => $times) {
                $is_working = isset($times['is_working']) ? 1 : 0;
                // Only insert if the day is marked as a working day and times are set
                if ($is_working && !empty($times['open_time']) && !empty($times['close_time'])) {
                    $insert_stmt->execute([
                        $user_id_to_update,
                        $day,
                        $times['open_time'],
                        $times['close_time'],
                        1
                    ]);
                }
            }

            $db->commit();
            $_SESSION['message'] = 'تم تحديث أوقات دوام الموظف بنجاح.';

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث الإعدادات: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'الرجاء تحديد موظف أولاً.';
    }

    // Redirect back to the same user's settings page
    header("Location: user_working_hours_settings.php?user_id=" . $user_id_to_update);
    exit;
}

// --- FETCH DATA FOR DISPLAY ---
try {
    // Fetch all users for the dropdown
    $users_stmt = $db->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC");
    $all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $user_schedule = [];
    if ($selected_user_id) {
        $schedule_stmt = $db->prepare("SELECT day_of_week, open_time, close_time, is_working FROM user_working_hours WHERE user_id = ?");
        $schedule_stmt->execute([$selected_user_id]);
        $schedule_data = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data by day of the week for easier access
        foreach ($schedule_data as $day_data) {
            $user_schedule[$day_data['day_of_week']] = $day_data;
        }
    }
} catch (Exception $e) {
    $all_users = [];
    $_SESSION['error_on_load'] = "خطأ في جلب البيانات: " . $e->getMessage();
}

$days_of_week = [
    'saturday'  => 'السبت',
    'sunday'    => 'الأحد',
    'monday'    => 'الإثنين',
    'tuesday'   => 'الثلاثاء',
    'wednesday' => 'الأربعاء',
    'thursday'  => 'الخميس',
    'friday'    => 'الجمعة',
];

// --- PAGE SETUP ---
$page_title = 'إعدادات دوام الموظفين';
$page_subtitle = 'تحديد ساعات العمل لكل موظف على حدة';
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
        <div class="w-10 h-10 bg-mai-brown rounded-lg flex items-center justify-center text-white"><i class="fas fa-user-clock"></i></div>
        <div>
            <h1 class="text-xl font-bold text-mai-dark-brown"><?= $page_title ?></h1>
            <p class="text-xs text-gray-500"><?= $page_subtitle ?></p>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div class="p-6" x-data="{ selectedUser: '<?= $selected_user_id ?>' }">

    <!-- Display Feedback Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?= $_SESSION['message'] ?></p></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error']) || isset($_SESSION['error_on_load'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?= $_SESSION['error'] ?? $_SESSION['error_on_load'] ?></p></div>
        <?php unset($_SESSION['error']); unset($_SESSION['error_on_load']); ?>
    <?php endif; ?>

    <!-- User Selection Card -->
    <div class="bg-white rounded-xl shadow-lg max-w-2xl mx-auto mb-8 p-6">
        <label for="user_selector" class="block text-lg font-bold text-mai-dark-brown mb-3">اختر الموظف</label>
        <select id="user_selector" x-model="selectedUser" @change="window.location.href = 'user_working_hours_settings.php?user_id=' + selectedUser"
                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-mai-brown focus:border-mai-brown p-3 text-lg">
            <option value="">-- الرجاء اختيار موظف --</option>
            <?php foreach ($all_users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= ($selected_user_id == $user['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Settings Form Card (Visible only when a user is selected) -->
    <?php if ($selected_user_id): ?>
    <div class="bg-white rounded-xl shadow-lg max-w-2xl mx-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-bold text-mai-dark-brown">تعديل أوقات الدوام</h2>
        </div>
        <form method="POST" class="p-8">
            <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
            <div class="space-y-4">
                <?php foreach ($days_of_week as $day_key => $day_name): 
                    $schedule_for_day = $user_schedule[$day_key] ?? null;
                    $is_working = $schedule_for_day && $schedule_for_day['is_working'];
                    $open_time = $schedule_for_day['open_time'] ?? '09:00:00';
                    $close_time = $schedule_for_day['close_time'] ?? '17:00:00';
                ?>
                <div x-data="{ isWorking: <?= $is_working ? 'true' : 'false' ?> }" class="p-4 border rounded-lg" :class="isWorking ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <!-- Day Name and Checkbox -->
                        <div class="md:col-span-1">
                             <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="working_hours[<?= $day_key ?>][is_working]" value="1" x-model="isWorking" class="h-5 w-5 rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                <span class="text-md font-bold text-gray-800"><?= $day_name ?></span>
                            </label>
                        </div>
                        <!-- Time Inputs -->
                        <div class="md:col-span-2 grid grid-cols-2 gap-4">
                             <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">وقت البدء</label>
                                <input type="time" name="working_hours[<?= $day_key ?>][open_time]" value="<?= htmlspecialchars(date('H:i', strtotime($open_time))) ?>" :disabled="!isWorking" class="w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-200 disabled:cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">وقت الانتهاء</label>
                                <input type="time" name="working_hours[<?= $day_key ?>][close_time]" value="<?= htmlspecialchars(date('H:i', strtotime($close_time))) ?>" :disabled="!isWorking" class="w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-200 disabled:cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-5 border-t border-gray-200 flex justify-end">
                <button type="submit" class="bg-mai-brown text-white px-8 py-3 rounded-lg hover:bg-mai-dark-brown focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-mai-brown font-semibold">
                    <i class="fas fa-save ml-2"></i>
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>