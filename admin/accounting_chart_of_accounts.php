<?php
// =================================================================
// MAI SALON - CHART OF ACCOUNTS MANAGEMENT PAGE
// =================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin();
$page_title = 'شجرة الحسابات (دليل الحسابات)';

// --- ACTION HANDLING (ADD, EDIT, DELETE) ---
$action = $_POST['action'] ?? null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($action) {
            case 'add_account':
                $stmt = $db->prepare("INSERT INTO accounting_charts_of_accounts (account_code, account_name_ar, account_name_en, account_type_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['account_code'], $_POST['account_name_ar'], $_POST['account_name_en'], $_POST['account_type_id']]);
                $_SESSION['success'] = 'تمت إضافة الحساب بنجاح.';
                break;
            case 'edit_account':
                $stmt = $db->prepare("UPDATE accounting_charts_of_accounts SET account_code = ?, account_name_ar = ?, account_name_en = ?, account_type_id = ? WHERE id = ?");
                $stmt->execute([$_POST['account_code'], $_POST['account_name_ar'], $_POST['account_name_en'], $_POST['account_type_id'], $_POST['account_id']]);
                $_SESSION['success'] = 'تم تحديث الحساب بنجاح.';
                break;
        }
        header('Location: accounting_chart_of_accounts.php');
        exit;
    }
} catch (PDOException $e) {
    // Check for duplicate entry error
    if ($e->errorInfo[1] == 1062) {
        $_SESSION['error'] = 'فشل العملية. كود الحساب موجود مسبقاً.';
    } else {
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
    }
    header('Location: accounting_chart_of_accounts.php');
    exit;
}

// --- DATA FETCHING ---
// EDITED LINE: Corrected the column name from 'at.name.name_ar' to 'at.name_ar' and aliased it.
$accounts = $db->query("
    SELECT coa.*, at.name_ar AS type_name_ar
    FROM accounting_charts_of_accounts coa
    JOIN accounting_account_types at ON coa.account_type_id = at.id
    ORDER BY coa.account_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$account_types = $db->query("SELECT * FROM accounting_account_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
            <p class="text-sm text-gray-500">إدارة جميع الحسابات المالية للشركة من أصول، التزامات، إيرادات، ومصروفات.</p>
        </div>
        <div>
            <button @click="$dispatch('open-modal', { id: 'account-modal' })" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 font-bold">
                <i class="fas fa-plus"></i> إضافة حساب جديد
            </button>
        </div>
    </div>
</div>


<!-- Accounts Table -->
<div class="bg-white rounded-xl shadow-md">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">كود الحساب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الحساب (عربي)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الحساب (إنجليزي)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع الحساب</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($account['account_code']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($account['account_name_ar']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($account['account_name_en']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($account['type_name_ar']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <button @click="$dispatch('open-modal', {
                            id: 'account-modal',
                            isEdit: true,
                            account: <?= htmlspecialchars(json_encode($account)) ?>
                        })" class="text-indigo-600 hover:text-indigo-900">تعديل</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<div x-data="accountModal()" @open-modal.window="openModal($event.detail)" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" @click="show = false">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="accounting_chart_of_accounts.php">
                <input type="hidden" name="action" :value="isEdit ? 'edit_account' : 'add_account'">
                <input type="hidden" name="account_id" :value="account.id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="isEdit ? 'تعديل حساب' : 'إضافة حساب جديد'"></h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="account_code" class="block text-sm font-medium text-gray-700">كود الحساب (رقم فريد)</label>
                            <input type="text" name="account_code" id="account_code" x-model="account.account_code" required class="mt-1 w-full p-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="account_name_ar" class="block text-sm font-medium text-gray-700">اسم الحساب (عربي)</label>
                            <input type="text" name="account_name_ar" id="account_name_ar" x-model="account.account_name_ar" required class="mt-1 w-full p-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="account_name_en" class="block text-sm font-medium text-gray-700">اسم الحساب (إنجليزي)</label>
                            <input type="text" name="account_name_en" id="account_name_en" x-model="account.account_name_en" class="mt-1 w-full p-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="account_type_id" class="block text-sm font-medium text-gray-700">نوع الحساب</label>
                            <select name="account_type_id" id="account_type_id" x-model="account.account_type_id" required class="mt-1 w-full p-2 border rounded-lg bg-white">
                                <option value="">-- اختر النوع --</option>
                                <?php foreach ($account_types as $type): ?>
                                    <!-- EDITED LINE: Changed $type['type_name_ar'] to $type['name_ar'] to match your table structure -->
                                    <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-mai-brown text-base font-medium text-white hover:bg-mai-dark-brown sm:ml-3 sm:w-auto sm:text-sm" x-text="isEdit ? 'حفظ التعديلات' : 'إنشاء الحساب'"></button>
                    <button type="button" @click="show = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function accountModal() {
        return {
            show: false,
            isEdit: false,
            account: {},
            openModal(detail) {
                this.isEdit = detail.isEdit || false;
                if (this.isEdit) {
                    this.account = { ...detail.account };
                } else {
                    this.account = { id: '', account_code: '', account_name_ar: '', account_name_en: '', account_type_id: '' };
                }
                this.show = true;
            }
        }
    }
</script>


<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>