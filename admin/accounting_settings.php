<?php
// =================================================================
// MAI SALON - ACCOUNTING SETTINGS PAGE
// =================================================================

require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'accounting_functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin();

// --- FORM HANDLING: SAVE SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settings_to_save = $_POST['settings'] ?? [];
        
        $db->beginTransaction();

        $stmt = $db->prepare(
            "INSERT INTO accounting_settings (setting_key, setting_value) 
             VALUES (:key, :value) 
             ON DUPLICATE KEY UPDATE setting_value = :value"
        );

        foreach ($settings_to_save as $key => $value) {
            // Ensure a value is selected, otherwise, don't save an empty string
            if (!empty($value)) {
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
        }

        $db->commit();
        $_SESSION['success'] = 'تم حفظ إعدادات المحاسبة بنجاح!';
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'فشل حفظ الإعدادات: ' . $e->getMessage();
    }
    header('Location: accounting_settings.php');
    exit;
}

// --- DATA FETCHING ---
$page_title = 'إعدادات المحاسبة';

// Fetch all existing settings
$current_settings = getAccountingSettings($db);

// Fetch all accounts to populate the dropdowns
try {
    $accounts_stmt = $db->query("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE is_active = 1 ORDER BY account_code ASC");
    $all_accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_accounts = [];
    $_SESSION['error'] = "خطأ في جلب شجرة الحسابات: " . $e->getMessage();
}

// This array defines the settings form
$settings_fields = [
    'default_cash_account_id' => 'الحساب الافتراضي للنقدية (الصندوق)',
    'default_card_account_id' => 'الحساب الافتراضي للبطاقة (البنك)',
    'default_sales_revenue_account_id' => 'حساب إيرادات مبيعات المنتجات',
    'default_service_revenue_account_id' => 'حساب إيرادات الخدمات',
    'default_vat_liability_account_id' => 'حساب ضريبة القيمة المضافة (التزام)',
    'default_sales_discount_account_id' => 'حساب الخصم المسموح به (خصم المبيعات)',
    'default_inventory_account_id' => 'حساب المخزون (أصل)',
    'default_cogs_account_id' => 'حساب تكلفة البضاعة المباعة (مصروف)'
];

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white text-xl">
            <i class="fas fa-cogs"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
            <p class="text-sm text-gray-500">من هنا يتم ربط العمليات المحاسبية التلقائية (مثل فواتير نقاط البيع) بالحسابات الصحيحة.</p>
        </div>
    </div>
</div>

<?php include 'partials/messages.php'; ?>

<form method="POST" action="accounting_settings.php" class="bg-white rounded-xl shadow-md p-6">
    <input type="hidden" name="save_settings" value="1">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($settings_fields as $key => $label): ?>
            <div>
                <label for="<?= $key ?>" class="block text-sm font-medium text-gray-800 mb-1"><?= $label ?></label>
                <select id="<?= $key ?>" name="settings[<?= $key ?>]" class="w-full p-2 border border-gray-300 rounded-lg bg-gray-50 focus:ring-mai-brown focus:border-mai-brown" required>
                    <option value="">-- اختر حساب --</option>
                    <?php foreach ($all_accounts as $account): ?>
                        <option value="<?= $account['id'] ?>" 
                            <?php if (isset($current_settings[$key]) && $current_settings[$key] == $account['id']) { echo ' selected'; } ?>>
                            [<?= htmlspecialchars($account['account_code']) ?>] <?= htmlspecialchars($account['account_name_ar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-8 border-t pt-6 flex justify-end">
        <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-bold text-lg transition-colors">
            <i class="fas fa-save mr-2"></i>
            حفظ الإعدادات
        </button>
    </div>
</form>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>