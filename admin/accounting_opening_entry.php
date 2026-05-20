<?php
// --- SETUP AND SECURITY ---
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';

checkAdminAuth();
// requirePermission('accounting.manage'); // You can create a specific permission for this

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin();

$opening_entry_exists = false;
try {
    $stmt = $db->prepare("SELECT id FROM accounting_journal_entries WHERE description = 'قيد افتتاحي للنظام'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $opening_entry_exists = true;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'خطأ في التحقق من القيد الافتتاحي.';
}

// --- FORM HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$opening_entry_exists) {
    try {
        $entry_date = $_POST['entry_date'];
        $account_ids = $_POST['account_id'];
        $debits = $_POST['debit'];
        $credits = $_POST['credit'];

        if (empty($entry_date) || empty($account_ids)) {
            throw new Exception('الرجاء إدخال التاريخ واختيار حساب واحد على الأقل.');
        }

        $total_debit = 0;
        $total_credit = 0;
        $items = [];

        foreach ($account_ids as $index => $acc_id) {
            $debit_val = floatval($debits[$index] ?? 0);
            $credit_val = floatval($credits[$index] ?? 0);

            if ($debit_val < 0 || $credit_val < 0) throw new Exception('لا يمكن أن تكون المبالغ سالبة.');
            if ($debit_val > 0 && $credit_val > 0) throw new Exception('لا يمكن إدخال مبلغ مدين ودائن في نفس السطر.');
            if ($debit_val > 0) {
                $items[] = ['account_id' => $acc_id, 'type' => 'debit', 'amount' => $debit_val];
                $total_debit += $debit_val;
            } elseif ($credit_val > 0) {
                $items[] = ['account_id' => $acc_id, 'type' => 'credit', 'amount' => $credit_val];
                $total_credit += $credit_val;
            }
        }
        
        if (abs($total_debit - $total_credit) > 0.01) {
            throw new Exception('القيد غير متوازن! يجب أن يتساوى إجمالي المدين مع إجمالي الدائن.');
        }
        if (empty($items)) {
            throw new Exception('الرجاء إدخال بعض الأرصدة.');
        }

        // --- DATABASE TRANSACTION ---
        $db->beginTransaction();
        
        // 1. Create the main journal entry
        $stmt_entry = $db->prepare("INSERT INTO accounting_journal_entries (entry_date, description, created_by) VALUES (?, 'قيد افتتاحي للنظام', ?)");
        $stmt_entry->execute([$entry_date, $admin['id']]);
        $entry_id = $db->lastInsertId();

        // 2. Insert the entry items
        $stmt_items = $db->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_items->execute([$entry_id, $item['account_id'], $item['type'], $item['amount']]);
        }
        
        $db->commit();
        
        $_SESSION['success'] = 'تم تسجيل القيد الافتتاحي بنجاح!';
        header('Location: accounting_trial_balance.php');
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = 'فشل تسجيل القيد: ' . $e->getMessage();
    }
}

// --- DATA FOR PAGE LOAD ---
$accounts = [];
if (!$opening_entry_exists) {
    try {
        $stmt_acc = $db->query("SELECT id, account_code, account_name FROM accounting_accounts WHERE is_active = 1 ORDER BY account_code ASC");
        $accounts = $stmt_acc->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['error'] = 'فشل في تحميل دليل الحسابات.';
    }
}


$page_title = 'القيد الافتتاحي';
ob_start();
?>
<div class="bg-white rounded-xl shadow-md p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
            <p class="text-sm text-gray-500">تُستخدم هذه الصفحة مرة واحدة فقط لتسجيل الأرصدة الأولية للنظام.</p>
        </div>
        <a href="accounting_dashboard.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">عودة للوحة المالية</a>
    </div>

    <?php if ($opening_entry_exists): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
            <p class="font-bold">مكتمل</p>
            <p>تم تسجيل القيد الافتتاحي للنظام مسبقاً. لا يمكن إضافة قيد افتتاحي آخر.</p>
        </div>
    <?php else: ?>
        <form method="POST" x-data="openingEntryManager()">
            <div class="mb-4 max-w-sm">
                <label for="entry_date" class="block text-sm font-medium text-gray-700">تاريخ القيد</label>
                <input type="date" id="entry_date" name="entry_date" value="<?= date('Y-m-d') ?>" required class="mt-1 w-full p-2 border rounded-lg">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-medium">الحساب</th>
                            <th class="px-4 py-3 text-right text-sm font-medium">مدين</th>
                            <th class="px-4 py-3 text-right text-sm font-medium">دائن</th>
                            <th class="px-4 py-3 text-center text-sm font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td class="p-2">
                                    <select :name="`account_id[${index}]`" class="w-full p-2 border rounded-lg" required>
                                        <option value="">-- اختر حساب --</option>
                                        <?php foreach ($accounts as $account): ?>
                                            <option value="<?= $account['id'] ?>">[<?= $account['account_code'] ?>] <?= htmlspecialchars($account['account_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input type="number" step="0.01" min="0" :name="`debit[${index}]`" x-model.number="row.debit" @input="validateRow(index, 'debit')" class="w-full p-2 border rounded-lg" placeholder="0.00">
                                </td>
                                <td class="p-2">
                                    <input type="number" step="0.01" min="0" :name="`credit[${index}]`" x-model.number="row.credit" @input="validateRow(index, 'credit')" class="w-full p-2 border rounded-lg" placeholder="0.00">
                                </td>
                                <td class="p-2 text-center">
                                    <button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="text-red-500 hover:text-red-700">&times; حذف</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="p-3 text-left font-bold">الإجمالي</td>
                            <td class="p-3">
                                <span class="font-bold text-lg" x-text="totals.debit.toFixed(2)"></span>
                            </td>
                            <td class="p-3">
                                <span class="font-bold text-lg" x-text="totals.credit.toFixed(2)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-4 flex justify-between items-center">
                <button type="button" @click="addRow()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">+ إضافة سطر</button>
                <div class="flex items-center gap-4">
                    <div x-show="!isBalanced()" class="text-red-600 font-semibold">القيد غير متوازن!</div>
                    <button type="submit" :disabled="!isBalanced() || totals.debit === 0" class="bg-green-600 text-white px-8 py-2 rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">حفظ القيد الافتتاحي</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('openingEntryManager', () => ({
        rows: [{ account_id: '', debit: '', credit: '' }],
        
        addRow() {
            this.rows.push({ account_id: '', debit: '', credit: '' });
        },
        removeRow(index) {
            this.rows.splice(index, 1);
        },
        validateRow(index, changedField) {
            if (changedField === 'debit' && this.rows[index].debit > 0) {
                this.rows[index].credit = '';
            } else if (changedField === 'credit' && this.rows[index].credit > 0) {
                this.rows[index].debit = '';
            }
        },
        get totals() {
            return this.rows.reduce((acc, row) => {
                acc.debit += parseFloat(row.debit || 0);
                acc.credit += parseFloat(row.credit || 0);
                return acc;
            }, { debit: 0, credit: 0 });
        },
        isBalanced() {
            const diff = Math.abs(this.totals.debit - this.totals.credit);
            return diff < 0.01;
        }
    }));
});
</script>

<?php
$page_content = ob_get_clean();
// Assuming you have Alpine.js included in your layout file
include 'layouts/responsive_base.php';
?>