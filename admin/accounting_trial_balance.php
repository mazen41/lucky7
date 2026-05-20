<?php
// --- SETUP AND SECURITY ---
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';

checkAdminAuth();
// requirePermission('accounting.view');

$database = new Database();
$db = $database->getConnection();

$trial_balance_data = [];
$grand_total_debit = 0;
$grand_total_credit = 0;

try {
    $sql = "
        SELECT
            acc.account_code,
            acc.account_name,
            acc.normal_balance,
            COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE 0 END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN item.type = 'credit' THEN item.amount ELSE 0 END), 0) AS total_credit
        FROM
            accounting_accounts acc
        LEFT JOIN
            accounting_entry_items item ON acc.id = item.account_id
        WHERE acc.is_active = 1
        GROUP BY
            acc.id, acc.account_code, acc.account_name, acc.normal_balance
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY
            acc.account_code ASC;
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $trial_balance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = 'فشل في جلب بيانات ميزان المراجعة: ' . $e->getMessage();
}


$page_title = 'ميزان المراجعة';
ob_start();
?>
<div class="bg-white rounded-xl shadow-md p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
            <p class="text-sm text-gray-500">يعرض هذا التقرير أرصدة جميع الحسابات للتأكد من توازن النظام المالي.</p>
        </div>
        <a href="accounting_dashboard.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">عودة للوحة المالية</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-medium">كود الحساب</th>
                    <th class="px-4 py-3 text-right text-sm font-medium">اسم الحساب</th>
                    <th class="px-4 py-3 text-right text-sm font-medium">إجمالي مدين</th>
                    <th class="px-4 py-3 text-right text-sm font-medium">إجمالي دائن</th>
                    <th class="px-4 py-3 text-right text-sm font-medium">الرصيد النهائي</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($trial_balance_data)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-10 text-gray-500">
                            لا توجد حركات مالية مسجلة لعرضها.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trial_balance_data as $row): ?>
                        <?php
                            $total_debit = (float)$row['total_debit'];
                            $total_credit = (float)$row['total_credit'];
                            
                            $grand_total_debit += $total_debit;
                            $grand_total_credit += $total_credit;

                            $balance = 0;
                            if ($row['normal_balance'] === 'debit') {
                                $balance = $total_debit - $total_credit;
                            } else {
                                $balance = $total_credit - $total_debit;
                            }
                        ?>
                        <tr>
                            <td class="px-4 py-4 whitespace-nowrap"><?= htmlspecialchars($row['account_code']) ?></td>
                            <td class="px-4 py-4 whitespace-nowrap font-medium text-gray-800"><?= htmlspecialchars($row['account_name']) ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-gray-600"><?= number_format($total_debit, 2) ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-gray-600"><?= number_format($total_credit, 2) ?></td>
                            <td class="px-4 py-4 whitespace-nowrap font-bold <?= $balance >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                                <?= number_format(abs($balance), 2) ?>
                                <span class="text-xs text-gray-500"><?= $balance >= 0 ? ($row['normal_balance'] === 'debit' ? 'مدين' : 'دائن') : ($row['normal_balance'] === 'debit' ? '(دائن)' : '(مدين)') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100">
                <tr>
                    <td colspan="2" class="px-4 py-4 text-left font-bold text-lg">الإجمالي الكلي</td>
                    <td class="px-4 py-4 text-right font-bold text-lg"><?= number_format($grand_total_debit, 2) ?> ريال</td>
                    <td class="px-4 py-4 text-right font-bold text-lg"><?= number_format($grand_total_credit, 2) ?> ريال</td>
                    <td class="px-4 py-4 text-center">
                        <?php if (abs($grand_total_debit - $grand_total_credit) < 0.01): ?>
                            <span class="px-3 py-1 text-sm rounded-full font-semibold bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i> متوازن
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 text-sm rounded-full font-semibold bg-red-100 text-red-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i> غير متوازن
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>