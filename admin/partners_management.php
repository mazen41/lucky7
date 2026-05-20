<?php
// =================================================================
// MAI SALON - PARTNERS MANAGEMENT (UPDATED)
// =================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$page_title = 'إدارة الشركاء';
$action = $_POST['action'] ?? null;

// --- ACTION HANDLER ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name']);
            $percentage = (float)$_POST['percentage'];
            $partner_id = $_POST['partner_id'] ?? null;

            // --- VALIDATION ---
            if (empty($name) || $percentage <= 0 || $percentage > 100) {
                throw new Exception('البيانات المدخلة غير صحيحة. يرجى مراجعة اسم الشريك والنسبة المئوية.');
            }

            // Check total percentage
            $total_percentage_query = "SELECT SUM(percentage) as total FROM partners";
            if ($action === 'edit') {
                $total_percentage_query .= " WHERE id != ?";
            }
            $stmt = $db->prepare($total_percentage_query);
            if ($action === 'edit') {
                 $stmt->execute([$partner_id]);
            } else {
                 $stmt->execute();
            }
            $current_total = (float)$stmt->fetchColumn();
            
            if (($current_total + $percentage) > 100.00) {
                throw new Exception('لا يمكن أن يتجاوز إجمالي نسب الشركاء 100%. الإجمالي الحالي: ' . $current_total . '%');
            }

            // --- DATABASE OPERATION ---
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO partners (name, percentage) VALUES (?, ?)");
                $stmt->execute([$name, $percentage]);
                $_SESSION['success'] = 'تمت إضافة الشريك بنجاح.';
            } else {
                $stmt = $db->prepare("UPDATE partners SET name = ?, percentage = ? WHERE id = ?");
                $stmt->execute([$name, $percentage, $partner_id]);
                $_SESSION['success'] = 'تم تحديث بيانات الشريك بنجاح.';
            }
        } elseif ($action === 'delete') {
            $partner_id = $_POST['partner_id'];
            // Basic check to prevent deleting a partner with a balance
            $balance_check_stmt = $db->prepare("SELECT current_balance FROM partners WHERE id = ?");
            $balance_check_stmt->execute([$partner_id]);
            $balance = (float)$balance_check_stmt->fetchColumn();

            if ($balance != 0) {
                 throw new Exception('لا يمكن حذف شريك لديه رصيد حالي. يجب تسوية الرصيد أولاً.');
            }

            $stmt = $db->prepare("DELETE FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $_SESSION['success'] = 'تم حذف الشريك بنجاح.';
        }

        header('Location: partners_management.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'فشل العملية: ' . $e->getMessage();
    header('Location: partners_management.php');
    exit;
}

// --- DATA FETCHING ---
$partners = $db->query("SELECT * FROM partners ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$total_percentage = $db->query("SELECT SUM(percentage) as total FROM partners")->fetchColumn() ?: 0;


ob_start();
?>

<!-- Alpine.js Modal Controller -->
<div x-data="partnerModal()" @open-modal.window="openModal($event.detail)" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" class="fixed inset-0 transition-opacity" @click="show = false"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="partners_management.php">
                <input type="hidden" name="action" :value="isEdit ? 'edit' : 'add'">
                <input type="hidden" name="partner_id" :value="partner.id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="isEdit ? 'تعديل بيانات شريك' : 'إضافة شريك جديد'"></h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">اسم الشريك</label>
                            <input type="text" name="name" x-model="partner.name" required class="mt-1 w-full p-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">نسبة الربح (%)</label>
                            <input type="number" step="0.01" min="0.01" max="100" name="percentage" x-model="partner.percentage" required placeholder="e.g., 50.00" class="mt-1 w-full p-2 border rounded-lg">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border shadow-sm px-4 py-2 bg-mai-brown text-base font-medium text-white hover:bg-mai-dark-brown sm:ml-3 sm:w-auto sm:text-sm" x-text="isEdit ? 'حفظ التعديلات' : 'إضافة شريك'"></button>
                    <button type="button" @click="show = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
                <p class="text-sm text-gray-500">إدارة الشركاء ونسب توزيع الأرباح وأرصدتهم الحالية.</p>
            </div>
            <div>
                <button @click="$dispatch('open-modal')" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 font-bold w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i> إضافة شريك جديد
                </button>
            </div>
        </div>
        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold text-lg">إجمالي النسب الموزعة: 
                <span class="<?= $total_percentage > 100 ? 'text-red-600' : 'text-green-600' ?>"><?= number_format($total_percentage, 2) ?>%</span>
                <?php if ($total_percentage != 100): ?>
                    <span class="text-sm font-normal text-yellow-600">(ملاحظة: الإجمالي لا يساوي 100%)</span>
                <?php endif; ?>
            </h3>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium">اسم الشريك</th>
                        <th class="px-6 py-3 text-center text-xs font-medium">النسبة المئوية</th>
                        <th class="px-6 py-3 text-center text-xs font-medium">الرصيد الحالي</th>
                        <th class="px-6 py-3 text-center text-xs font-medium">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($partners)): ?>
                        <tr><td colspan="4" class="text-center py-10 text-gray-500">لم يتم إضافة شركاء بعد.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($partners as $partner): ?>
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($partner['name']) ?></td>
                        <td class="px-6 py-4 text-center text-gray-600 font-mono"><?= htmlspecialchars($partner['percentage']) ?>%</td>
                        <td class="px-6 py-4 text-center text-gray-800 font-bold font-mono">
                            <?= number_format($partner['current_balance'], 2) ?> ر.س
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-4">
                            <button @click="$dispatch('open-modal', { isEdit: true, partner: <?= htmlspecialchars(json_encode($partner)) ?> })" class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit mr-1"></i> تعديل
                            </button>
                            <form method="POST" action="partners_management.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا الشريك؟');" class="inline-block">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash mr-1"></i> حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('partnerModal', () => ({
        show: false,
        isEdit: false,
        partner: {},
        openModal(detail) {
            this.isEdit = detail?.isEdit || false;
            this.partner = this.isEdit ? { ...detail.partner } : { id: '', name: '', percentage: '' };
            this.show = true;
        }
    }));
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>