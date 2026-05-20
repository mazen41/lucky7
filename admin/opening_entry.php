<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'accounting_functions.php'; // Important!

checkAdminAuth();
$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// Check if an opening entry already exists
$opening_entry_exists = false;
try {
    $stmt = $db->prepare("SELECT id FROM accounting_journal_entries WHERE description = 'قيد افتتاحي - Opening Balance' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetch()) {
        $opening_entry_exists = true;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "خطأ في التحقق من القيد الافتتاحي: " . $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$opening_entry_exists) {
    requirePermission('accounting.manage');
    $accounts = $_POST['accounts'] ?? [];
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
    $current_admin_id = $admin['id'];
    
    $total_debits = 0;
    $total_credits = 0;
    $journal_items = [];

    foreach ($accounts as $account_id => $values) {
        $debit = round(floatval($values['debit'] ?? 0), 2);
        $credit = round(floatval($values['credit'] ?? 0), 2);

        if ($debit > 0) {
            $total_debits += $debit;
            $journal_items[] = ['account_id' => $account_id, 'type' => 'debit', 'amount' => $debit];
        }
        if ($credit > 0) {
            $total_credits += $credit;
            $journal_items[] = ['account_id' => $account_id, 'type' => 'credit', 'amount' => $credit];
        }
    }

    if (abs($total_debits - $total_credits) > 0.01) {
        $_SESSION['error'] = "القيد غير متوازن! إجمالي المدين: {$total_debits}, إجمالي الدائن: {$total_credits}.";
    } elseif (empty($journal_items)) {
        $_SESSION['error'] = "لم يتم إدخال أي أرصدة.";
    } else {
        try {
            $db->beginTransaction();
            createJournalEntry(
                $db,
                $entry_date,
                'قيد افتتاحي - Opening Balance',
                $journal_items,
                null,
                'opening_balance',
                $current_admin_id
            );
            $db->commit();
            $_SESSION['message'] = 'تم تسجيل القيد الافتتاحي بنجاح.';
            header('Location: opening_entry.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'فشل تسجيل القيد الافتتاحي: ' . $e->getMessage();
        }
    }
    header('Location: opening_entry.php');
    exit;
}

// Fetch chart of accounts for the form
try {
    $accounts_stmt = $db->query("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE is_active = 1 ORDER BY account_code ASC");
    $chart_of_accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $chart_of_accounts = [];
    $_SESSION['error'] = "خطأ في جلب شجرة الحسابات: " . $e->getMessage();
}

$page_title = 'القيد الافتتاحي';
ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-6 p-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center text-white">
            <i class="fas fa-play-circle text-xl"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-mai-dark-brown">القيد الافتتاحي</h1>
            <p class="text-sm text-gray-500">تُستخدم هذه الصفحة مرة واحدة فقط لتسجيل أرصدة البداية عند تشغيل النظام.</p>
        </div>
    </div>
</div>

<!-- Display Messages -->
<?php include 'partials/messages.php'; ?>

<?php if ($opening_entry_exists): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg" role="alert">
        <p class="font-bold">تنبيه!</p>
        <p>لقد تم تسجيل القيد الافتتاحي مسبقاً. لا يمكن إنشاء قيد افتتاحي آخر.</p>
    </div>
<?php else: ?>
<form method="POST" action="opening_entry.php" id="opening-entry-form" class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b">
        <label for="entry_date" class="block text-sm font-medium text-gray-700">تاريخ القيد</label>
        <input type="date" name="entry_date" id="entry_date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full max-w-xs border-gray-300 rounded-md shadow-sm">
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">كود الحساب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الحساب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مدين</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دائن</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($chart_of_accounts as $account): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($account['account_code']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($account['account_name_ar']); ?></td>
                    <td class="px-6 py-4">
                        <input type="number" step="0.01" name="accounts[<?php echo $account['id']; ?>][debit]" class="w-full text-right border-gray-300 rounded-md shadow-sm debit-input" placeholder="0.00">
                    </td>
                    <td class="px-6 py-4">
                        <input type="number" step="0.01" name="accounts[<?php echo $account['id']; ?>][credit]" class="w-full text-right border-gray-300 rounded-md shadow-sm credit-input" placeholder="0.00">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-100">
                <tr>
                    <td colspan="2" class="px-6 py-3 text-left font-bold text-gray-700">الإجمالي</td>
                    <td class="px-6 py-3">
                        <input type="text" id="total_debit" class="w-full text-right bg-gray-200 border-gray-300 rounded-md font-bold" readonly>
                    </td>
                    <td class="px-6 py-3">
                        <input type="text" id="total_credit" class="w-full text-right bg-gray-200 border-gray-300 rounded-md font-bold" readonly>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="p-4 bg-gray-50 flex justify-end items-center gap-4">
         <div id="balance-status" class="font-bold text-lg"></div>
         <button type="submit" class="px-6 py-3 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown font-bold">
            <i class="fas fa-save"></i> حفظ القيد الافتتاحي
         </button>
    </div>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('opening-entry-form');
    if (!form) return;

    const debitInputs = form.querySelectorAll('.debit-input');
    const creditInputs = form.querySelectorAll('.credit-input');
    const totalDebitEl = document.getElementById('total_debit');
    const totalCreditEl = document.getElementById('total_credit');
    const balanceStatusEl = document.getElementById('balance-status');

    function calculateTotals() {
        let totalDebit = 0;
        debitInputs.forEach(input => {
            totalDebit += parseFloat(input.value) || 0;
        });

        let totalCredit = 0;
        creditInputs.forEach(input => {
            totalCredit += parseFloat(input.value) || 0;
        });

        totalDebitEl.value = totalDebit.toFixed(2);
        totalCreditEl.value = totalCredit.toFixed(2);
        
        if (Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0) {
            balanceStatusEl.textContent = 'متوازن';
            balanceStatusEl.className = 'font-bold text-lg text-green-600';
        } else {
            balanceStatusEl.textContent = 'غير متوازن';
            balanceStatusEl.className = 'font-bold text-lg text-red-600';
        }
    }

    debitInputs.forEach(input => input.addEventListener('input', calculateTotals));
    creditInputs.forEach(input => input.addEventListener('input', calculateTotals));

    calculateTotals();
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>