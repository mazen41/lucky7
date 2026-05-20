<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

 $database = new Database();
 $db = $database->getConnection();

/**
 * Get site settings from the database.
 * This version specifically checks the status of the 'tax_rate' setting 
 * to determine if tax is enabled system-wide.
 */
function getSiteSettings($db) {
    $settings = [];
    try {
        // Ensure the table exists before querying
        $stmt = $db->prepare("SHOW TABLES LIKE 'site_settings'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            throw new Exception("The site_settings table does not exist.");
        }

        // Fetch all settings including their status
        $stmt = $db->prepare("SELECT `setting_key`, `setting_value`, `status` FROM site_settings");
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];

            if ($row['setting_key'] === 'tax_rate') {
                $settings['tax_enabled'] = ($row['status'] === 'active') ? '1' : '0';
            }
        }
    } catch (Exception $e) {
        // Return safe defaults if the table or settings don't exist
        error_log("Settings Error: " . $e->getMessage());
        $settings = [
            'tax_enabled' => '0',
            'tax_rate' => '15.00',
            'tax_name' => 'ضريبة القيمة المضافة',
            'tax_inclusive' => '1',
            'tax_number' => '' // NEW: Default value for the tax number
        ];
    }
    return $settings;
}

 $settings = getSiteSettings($db);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tax_settings'])) {
    
    $tax_rate_status = isset($_POST['tax_enabled']) ? 'active' : 'inactive';
    
    $tax_rate = $_POST['tax_rate'] ?? '15.00';
    $tax_name = $_POST['tax_name'] ?? 'ضريبة القيمة المضافة';
    $tax_number = $_POST['tax_number'] ?? ''; // NEW: Get tax number from form
    $tax_type = $_POST['tax_type'] ?? 'inclusive';
    $tax_inclusive = ($tax_type === 'inclusive') ? '1' : '0';

    try {
        $db->beginTransaction();

        $stmt_tax_rate = $db->prepare(
            "UPDATE site_settings SET `setting_value` = ?, `status` = ? WHERE `setting_key` = 'tax_rate'"
        );
        $stmt_tax_rate->execute([$tax_rate, $tax_rate_status]);

        $other_settings_to_update = [
            'tax_name' => $tax_name,
            'tax_inclusive' => $tax_inclusive,
            'tax_number' => $tax_number // NEW: Add tax number to the update array
        ];

        foreach ($other_settings_to_update as $setting_key => $setting_value) {
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE `setting_key` = ?");
            $check_stmt->execute([$setting_key]);
            $exists = $check_stmt->fetchColumn();

            if ($exists) {
                $stmt = $db->prepare("UPDATE site_settings SET `setting_value` = ? WHERE `setting_key` = ?");
                $stmt->execute([$setting_value, $setting_key]);
            } else {
                $stmt = $db->prepare("INSERT INTO site_settings (`setting_key`, `setting_value`) VALUES (?, ?)");
                $stmt->execute([$setting_key, $setting_value]);
            }
        }
        
        $db->commit();
        $_SESSION['success_message'] = 'تم تحديث إعدادات الضرائب بنجاح';
        header('Location: tax_settings.php');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = 'حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage();
    }
}

// Layout configuration
 $page_title = 'إعدادات الضرائب';
 $page_subtitle = 'إدارة الضرائب والإعدادات المالية';

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">إعدادات الضرائب</h1>
                <p class="text-xs text-gray-500">إدارة الضرائب والإعدادات المالية</p>
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
    <!-- Navigation Menu (Optional, for context) -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="settings.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 flex items-center">
            <i class="fas fa-cog ml-2"></i>
            الإعدادات الرئيسية
        </a>
        <a href="tax_settings.php" class="px-4 py-2 bg-mai-brown text-white rounded-lg flex items-center font-medium">
            <i class="fas fa-percentage ml-2"></i>
            إعدادات الضرائب
        </a>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Tax Settings Form -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-mai-dark-brown mb-6">تعديل إعدادات الضرائب</h2>
        <form method="POST" class="space-y-6">
            <div class="flex items-center space-x-3 space-x-reverse bg-gray-50 p-3 rounded-lg">
                <input type="checkbox" id="tax_enabled" name="tax_enabled" 
                       <?php echo (isset($settings['tax_enabled']) && $settings['tax_enabled'] == '1') ? 'checked' : ''; ?>
                       class="h-5 w-5 text-mai-brown focus:ring-mai-brown border-gray-300 rounded">
                <label for="tax_enabled" class="text-base font-medium text-gray-800">تفعيل الضرائب على الفواتير</label>
            </div>
            
            <div id="tax-details" class="space-y-4 border-t pt-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">معدل الضريبة (%)</label>
                        <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" 
                               value="<?php echo $settings['tax_rate'] ?? '15.00'; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    </div>
                    <div>
                        <label for="tax_name" class="block text-sm font-medium text-gray-700 mb-2">اسم الضريبة</label>
                        <input type="text" id="tax_name" name="tax_name" 
                               value="<?php echo htmlspecialchars($settings['tax_name'] ?? 'ضريبة القيمة المضافة'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    </div>
                    
                    <!-- NEW: Tax Number Field -->
                    <div class="md:col-span-2">
                        <label for="tax_number" class="block text-sm font-medium text-gray-700 mb-2">الرقم الضريبي</label>
                        <input type="text" id="tax_number" name="tax_number" 
                               value="<?php echo htmlspecialchars($settings['tax_number'] ?? ''); ?>"
                               placeholder="أدخل الرقم الضريبي للشركة"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    </div>
                    <!-- END NEW -->
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">نوع الضريبة</label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="radio" id="tax_inclusive" name="tax_type" value="inclusive" 
                                   <?php echo (isset($settings['tax_inclusive']) && $settings['tax_inclusive'] == '0') ? '' : 'checked'; // Default to checked if not 0 ?>
                                   class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300">
                            <label for="tax_inclusive" class="mr-2 text-sm text-gray-700">شاملة للضريبة (الأسعار المعروضة تتضمن الضريبة)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="tax_exclusive" name="tax_type" value="exclusive" 
                                   <?php echo (isset($settings['tax_inclusive']) && $settings['tax_inclusive'] == '0') ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300">
                            <label for="tax_exclusive" class="mr-2 text-sm text-gray-700">مضافة للسعر (الضريبة تُضاف فوق السعر الأساسي)</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t pt-4">
                <button type="submit" name="update_tax_settings" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const taxEnabledCheckbox = document.getElementById('tax_enabled');
    const taxDetailsContainer = document.getElementById('tax-details');
    
    function toggleTaxDetailsVisibility() {
        if (taxEnabledCheckbox.checked) {
            taxDetailsContainer.style.display = 'block';
        } else {
            taxDetailsContainer.style.display = 'none';
        }
    }
    
    taxEnabledCheckbox.addEventListener('change', toggleTaxDetailsVisibility);
    
    toggleTaxDetailsVisibility();
});
</script>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>