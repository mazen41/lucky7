<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

 $database = new Database();
 $db = $database->getConnection();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password && strlen($new_password) >= 6) {
        // Get admin information
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin_data && password_verify($current_password, $admin_data['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $admin_id]);
            
            $_SESSION['success_message'] = 'تم تحديث كلمة المرور بنجاح';
        } else {
            $_SESSION['error_message'] = 'كلمة المرور الحالية غير صحيحة';
        }
    } else {
        $_SESSION['error_message'] = 'كلمة المرور الجديدة غير متطابقة أو قصيرة جداً';
    }
}

// Get current admin info
 $admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

// Layout configuration
 $page_title = 'إعدادات الحساب';
 $page_subtitle = 'إدارة الحساب وكلمة المرور';

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">إعدادات الحساب</h1>
                <p class="text-xs text-gray-500">إدارة الحساب وكلمة المرور</p>
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
        <a href="account_settings.php" class="px-4 py-2 bg-mai-brown text-white rounded-lg flex items-center">
            <i class="fas fa-user-cog ml-2"></i>
            إعدادات الحساب
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Password Change Section -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-mai-cream rounded-full flex items-center justify-center ml-4">
                    <i class="fas fa-key text-mai-brown text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-mai-dark-brown">تغيير كلمة المرور</h3>
                    <p class="text-gray-600 text-sm">تأكد من كلمة المرور الجديدة</p>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                </div>
                
                <button type="submit" name="update_password" 
                        class="w-full bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    تغيير كلمة المرور
                </button>
            </form>
        </div>

        <!-- System Information -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-mai-cream rounded-full flex items-center justify-center ml-4">
                    <i class="fas fa-info-circle text-mai-brown text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-mai-dark-brown">معلومات النظام</h3>
                    <p class="text-gray-600 text-sm">تفاصيل الخادم وقاعدة البيانات</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                    <span class="text-sm font-medium text-gray-700">إصدار PHP</span>
                    <span class="text-sm text-mai-brown font-bold"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                    <span class="text-sm font-medium text-gray-700">حالة قاعدة البيانات</span>
                    <span class="text-sm text-green-600 font-bold">
                        <i class="fas fa-circle text-xs"></i> متصل
                    </span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                    <span class="text-sm font-medium text-gray-700">المستخدم الحالي</span>
                    <span class="text-sm text-mai-brown font-bold"><?php echo htmlspecialchars($admin['username']); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                    <span class="text-sm font-medium text-gray-700">معرف المستخدم</span>
                    <span class="text-sm text-mai-brown font-bold">#<?php echo htmlspecialchars($admin['id']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>