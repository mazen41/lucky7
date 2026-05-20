<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();
// if (!hasPermission('settings.edit')) {
//     $_SESSION['error'] = 'ليس لديك الصلاحية لتعديل إعدادات الحجوزات.';
//     header('Location: dashboard.php');
//     exit();
// }

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_settings') {
    try {
        $slot_interval = intval($_POST['slot_interval']);
        $default_buffer = intval($_POST['default_buffer']);
        
        $stmt = $db->prepare("UPDATE booking_settings SET slot_interval = ?, default_buffer = ? WHERE id = 1");
        $stmt->execute([$slot_interval, $default_buffer]);
        
        $_SESSION['message'] = "تم تحديث إعدادات الحجوزات بنجاح.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch current settings
try {
    $stmt = $db->prepare("SELECT * FROM booking_settings WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no settings row exists, create one with defaults
    if (!$settings) {
        $db->query("INSERT INTO booking_settings (id, slot_interval, default_buffer) VALUES (1, 30, 10) ON DUPLICATE KEY UPDATE id=1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $settings = ['slot_interval' => 30, 'default_buffer' => 10]; // Fallback defaults
    $_SESSION['error'] = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

$page_title = 'إعدادات الحجوزات';
ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow p-4 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">إعدادات الحجوزات</h1>
                <p class="text-xs text-gray-500">التحكم في فترات المواعيد والأوقات الفاصلة</p>
            </div>
        </div>
        <!-- START: ADDED BUTTON -->
        <a href="/admin/settings.php" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 font-semibold hover:bg-gray-300 transition-colors text-sm">
            <i class="fas fa-arrow-left ml-2"></i>
            <span>العودة للإعدادات</span>
        </a>
        <!-- END: ADDED BUTTON -->
    </div>
</div>


<!-- Session Messages -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?= $_SESSION['message'] ?></p></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?= $_SESSION['error'] ?></p></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>


<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-xl font-bold text-mai-dark-brown mb-6">إدارة إعدادات الحجوزات</h3>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="space-y-6">
            <!-- Slot Interval -->
            <div>
                <label for="slot_interval" class="block text-sm font-medium text-gray-700 mb-1">الفاصل الزمني بين بداية كل موعد (الدقائق)</label>
                <p class="text-xs text-gray-500 mb-2">هذا يحدد الأوقات المتاحة التي يمكن للعميل الاختيار منها (مثال: 09:00, 09:30, 10:00).</p>
                <select id="slot_interval" name="slot_interval" class="w-full md:w-1/3 border-gray-300 rounded-lg shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                    <option value="15" <?= ($settings['slot_interval'] ?? 30) == 15 ? 'selected' : '' ?>>15 دقيقة</option>
                    <option value="30" <?= ($settings['slot_interval'] ?? 30) == 30 ? 'selected' : '' ?>>30 دقيقة</option>
                    <option value="60" <?= ($settings['slot_interval'] ?? 30) == 60 ? 'selected' : '' ?>>60 دقيقة</option>
                </select>
            </div>
            
            <!-- Default Buffer Time -->
            <div>
                <label for="default_buffer" class="block text-sm font-medium text-gray-700 mb-1">وقت التنظيف/الفاصل الافتراضي بين الحجوزات (الدقائق)</label>
                <p class="text-xs text-gray-500 mb-2">هذا هو الوقت الذي يتم إضافته بعد كل موعد للسماح بالتنظيف. يمكن تخصيصه لكل خدمة على حدة من صفحة الخدمات.</p>
                <input type="number" id="default_buffer" name="default_buffer" value="<?= htmlspecialchars($settings['default_buffer'] ?? 10) ?>" min="0" class="w-full md:w-1/3 border-gray-300 rounded-lg shadow-sm focus:ring-mai-brown focus:border-mai-brown">
            </div>
        </div>
        
        <div class="mt-8 border-t pt-5">
            <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-opacity-90">
                <i class="fas fa-save ml-2"></i>حفظ الإعدادات
            </button>
        </div>
    </form>
</div>

<?php
$page_content = ob_get_clean();
// Make sure you are using a layout file that matches your admin panel structure
include 'layouts/responsive_base.php'; 
?>