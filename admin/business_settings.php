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
            'company_name' => 'MAI SALON',
            'company_address' => 'الرياض، المملكة العربية السعودية',
            'company_phone' => '+966XXXXXXX',
            'company_email' => 'info@maisalon.com',
            'company_twitter' => '',
            'company_instagram' => ''
        ];
    }
    return $settings;
}

 $settings = getSettings($db);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business_info'])) {
    $company_name = $_POST['company_name'] ?? '';
    $company_address = $_POST['company_address'] ?? '';
    $company_phone = $_POST['company_phone'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_twitter = $_POST['company_twitter'] ?? '';
    $company_instagram = $_POST['company_instagram'] ?? '';

    try {
        // Update or insert settings
        $settings_to_update = [
            'company_name' => $company_name,
            'company_address' => $company_address,
            'company_phone' => $company_phone,
            'company_email' => $company_email,
            'company_twitter' => $company_twitter,
            'company_instagram' => $company_instagram
        ];

        foreach ($settings_to_update as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) 
                                VALUES (?, ?, 'string') 
                                ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $_SESSION['success_message'] = 'تم تحديث معلومات الشركة بنجاح';
        header('Location: /admin/business_settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage();
    }
}

// Layout configuration
 $page_title = 'إعدادات الشركة';
 $page_subtitle = 'إدارة معلومات الشركة والاتصال';

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">معلومات الشركة</h1>
                <p class="text-xs text-gray-500">إدارة معلومات الشركة والاتصال</p>
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
        <a href="business_settings.php" class="px-4 py-2 bg-mai-brown text-white rounded-lg flex items-center">
            <i class="fas fa-building ml-2"></i>
            معلومات الشركة
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

    <!-- Business Information Form -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-mai-dark-brown mb-6">معلومات الشركة</h2>
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم الشركة</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                    <input type="text" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الشركة</label>
                <textarea name="company_address" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                    <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">رابط تويتر</label>
                    <input type="url" name="company_twitter" value="<?php echo htmlspecialchars($settings['company_twitter'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رابط انستجرام</label>
                <input type="url" name="company_instagram" value="<?php echo htmlspecialchars($settings['company_instagram'] ?? ''); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
            </div>
            
            <button type="submit" name="update_business_info" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown transition-colors">
                <i class="fas fa-save ml-2"></i>
                حفظ معلومات الشركة
            </button>
        </form>
    </div>
</div>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>