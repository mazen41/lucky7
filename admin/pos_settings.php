<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

 $database = new Database();
 $db = $database->getConnection();

// Get current settings
function getSettings($db) {
    $settings = [];
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'settings'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            throw new Exception("Settings table does not exist");
        }

        $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            switch ($row['type']) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'decimal':
                    $value = (float) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $settings[$row['key']] = $value;
        }
    } catch (Exception $e) {
        $settings = [
            'pos_require_open_shift_for_cash' => true,
            'pos_auto_print_receipt' => true,
            'pos_default_payment_method' => 'cash'
        ];
    }
    return $settings;
}

 $settings = getSettings($db);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pos_settings'])) {
    $require_shift = isset($_POST['pos_require_open_shift_for_cash']) ? 1 : 0;
    $auto_print = isset($_POST['pos_auto_print_receipt']) ? 1 : 0;
    $default_payment = $_POST['pos_default_payment_method'] ?? 'cash';

    try {
        // Update or insert settings
        $settings_to_update = [
            'pos_require_open_shift_for_cash' => $require_shift,
            'pos_auto_print_receipt' => $auto_print,
            'pos_default_payment_method' => $default_payment
        ];

        foreach ($settings_to_update as $key => $value) {
            $type = 'string';
            if ($key === 'pos_require_open_shift_for_cash' || $key === 'pos_auto_print_receipt') {
                $type = 'boolean';
            }

            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$key, $value, $type, $value]);
        }

        $_SESSION['success_message'] = 'تم تحديث إعدادات نقطة البيع بنجاح';
        header('Location: pos_settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage();
    }
}

// Layout configuration
 $page_title = 'إعدادات نقطة البيع';
 $page_subtitle = 'إعدادات نظام نقطة البيع';

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">إعدادات نقطة البيع</h1>
                <p class="text-xs text-gray-500">إعدادات نظام نقطة البيع</p>
            </div>
        </div>
        <a href="settings.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-arrow-right ml-2"></i>
            العودة للإعدادات
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="container mx-auto px-4 pb-8">
    <!-- Navigation Menu -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="dashboard.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 flex items-center">
            <i class="fas fa-tachometer-alt ml-2"></i>
            لوحة التحكم
        </a>
        <a href="settings.php" class="px-4 py-2 bg-mai-cream text-mai-brown rounded-lg flex items-center font-medium">
            <i class="fas fa-cog ml-2"></i>
            الإعدادات
        </a>
        <a href="settings_pos.php" class="px-4 py-2 bg-mai-brown text-white rounded-lg flex items-center">
            <i class="fas fa-cash-register ml-2"></i>
            إعدادات نقطة البيع
        </a>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <!-- POS Settings Form -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-mai-dark-brown mb-6">إعدادات نقطة البيع</h2>
        <form method="POST" class="space-y-6">
            <div class="space-y-4">
                <div class="flex items-center space-x-3 space-x-reverse">
                    <input type="checkbox" id="pos_require_open_shift" name="pos_require_open_shift_for_cash" 
                           <?php echo ($settings['pos_require_open_shift_for_cash'] ?? true) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300 rounded">
                    <label for="pos_require_open_shift" class="text-sm font-medium text-gray-700">يتطلب فتح وردية للدفع النقدي</label>
                </div>
                
                <div class="flex items-center space-x-3 space-x-reverse">
                    <input type="checkbox" id="pos_auto_print" name="pos_auto_print_receipt" 
                           <?php echo ($settings['pos_auto_print_receipt'] ?? true) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300 rounded">
                    <label for="pos_auto_print" class="text-sm font-medium text-gray-700">طباعة الفاتورة تلقائياً</label>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">طريقة الدفع الافتراضية</label>
                <select name="pos_default_payment_method" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    <option value="cash" <?php echo ($settings['pos_default_payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>نقدي</option>
                    <option value="card" <?php echo ($settings['pos_default_payment_method'] ?? 'cash') === 'card' ? 'selected' : ''; ?>>بطاقة</option>
                    <option value="transfer" <?php echo ($settings['pos_default_payment_method'] ?? 'cash') === 'transfer' ? 'selected' : ''; ?>>تحويل بنكي</option>
                </select>
            </div>
            
            <button type="submit" name="update_pos_settings" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown transition-colors">
                <i class="fas fa-save ml-2"></i>
                حفظ إعدادات نقطة البيع
            </button>
        </form>
    </div>
</div>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>