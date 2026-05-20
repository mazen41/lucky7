<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'accounting_functions.php';

checkAdminAuth();
requirePermission('accounting.view');

$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// --- Date Filtering ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- Main Data Fetching for Trial Balance ---
$trial_balance_data = [];
$total_debit = 0;
$total_credit = 0;

try {
    // This query calculates the opening balance before the start_date, 
    // adds transactions within the date range, and gives the final balances.
    $sql = "
        SELECT 
            coa.id,
            coa.account_code, 
            coa.account_name_ar,
            act.normal_balance,
            
            -- Calculate Opening Balance (before start_date)
            COALESCE(SUM(CASE WHEN aei.type = 'debit' AND aje.entry_date < :start_date THEN aei.amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN aei.type = 'credit' AND aje.entry_date < :start_date THEN aei.amount ELSE 0 END), 0) 
            AS opening_balance_raw,
            
            -- Calculate transactions within the period
            COALESCE(SUM(CASE WHEN aei.type = 'debit' AND aje.entry_date BETWEEN :start_date AND :end_date THEN aei.amount ELSE 0 END), 0) 
            AS period_debit,
            COALESCE(SUM(CASE WHEN aei.type = 'credit' AND aje.entry_date BETWEEN :start_date AND :end_date THEN aei.amount ELSE 0 END), 0) 
            AS period_credit

        FROM accounting_charts_of_accounts coa
        LEFT JOIN accounting_account_types act ON coa.account_type_id = act.id
        LEFT JOIN accounting_entry_items aei ON coa.id = aei.account_id
        LEFT JOIN accounting_journal_entries aje ON aei.entry_id = aje.id
        GROUP BY coa.id, coa.account_code, coa.account_name_ar, act.normal_balance
        ORDER BY coa.account_code ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $opening_balance = ($row['normal_balance'] == 'debit') ? $row['opening_balance_raw'] : -$row['opening_balance_raw'];
        
        $final_balance_raw = $row['opening_balance_raw'] + $row['period_debit'] - $row['period_credit'];

        // Only show accounts that have activity
        if ($opening_balance != 0 || $row['period_debit'] != 0 || $row['period_credit'] != 0) {
             $trial_balance_data[] = [
                'code' => $row['account_code'],
                'name' => $row['account_name_ar'],
                'debit' => $row['period_debit'],
                'credit' => $row['period_credit'],
                'balance' => $final_balance_raw
            ];
            $total_debit += $row['period_debit'];
            $total_credit += $row['period_credit'];
        }
    }

} catch (Exception $e) {
    $_SESSION['error'] = "خطأ في جلب بيانات ميزان المراجعة: " . $e->getMessage();
}


$page_title = 'التقارير المالية';
ob_start();
?>

<div x-data="{ activeTab: 'trial_balance' }">
    <!-- Page Header & Date Filter -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-mai-dark-brown">التقارير المالية</h1>
                    <p class="text-sm text-gray-500">نظرة شاملة على الأداء المالي للصالون.</p>
                </div>
            </div>
            <form method="GET" class="flex items-center gap-3 text-sm">
                <div>
                    <label for="start_date" class="font-semibold">من:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="end_date" class="font-semibold">إلى:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="border-gray-300 rounded-md shadow-sm">
                </div>
                <button type="submit" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">
                    <i class="fas fa-filter"></i> تطبيق
                </button>
            </form>
        </div>
    </div>
    
    <!-- Display Messages -->
    <?php include 'partials/messages.php'; ?>

    <!-- Summary Cards (Placeholder for now) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <div class="bg-white p-5 rounded-xl shadow"><h4 class="text-gray-500">إجمالي الإيرادات</h4><p class="text-2xl font-bold text-green-600">... ر.س</p></div>
        <div class="bg-white p-5 rounded-xl shadow"><h4 class="text-gray-500">إجمالي المصروفات</h4><p class="text-2xl font-bold text-red-600">... ر.س</p></div>
        <div class="bg-white p-5 rounded-xl shadow"><h4 class="text-gray-500">صافي الربح</h4><p class="text-2xl font-bold text-blue-600">... ر.س</p></div>
        <div class="bg-white p-5 rounded-xl shadow"><h4 class="text-gray-500">إجمالي الأصول</h4><p class="text-2xl font-bold">... ر.س</p></div>
        <div class="bg-white p-5 rounded-xl shadow"><h4 class="text-gray-500">إجمالي الالتزامات</h4><p class="text-2xl font-bold">... ر.س</p></div>
    </div>

    <!-- Tabs Navigation -->
    <div class="mb-4 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
            <li class="mr-2">
                <button @click="activeTab = 'trial_balance'" :class="{ 'text-blue-600 border-blue-600 active': activeTab === 'trial_balance', 'border-transparent hover:text-gray-600 hover:border-gray-300': activeTab !== 'trial_balance' }" class="inline-block p-4 border-b-2 rounded-t-lg">
                    ميزان المراجعة (Trial Balance)
                </button>
            </li>
            <!-- Add other tabs here later -->
        </ul>
    </div>

    <!-- Tabs Content -->
    <div>
        <!-- Trial Balance Tab -->
        <div x-show="activeTab === 'trial_balance'" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
             <div class="p-4 border-b">
                <h3 class="text-lg font-bold">ميزان المراجعة للفترة من <?php echo htmlspecialchars($start_date); ?> إلى <?php echo htmlspecialchars($end_date); ?></h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">كود الحساب</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الحساب</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مدين (حركة)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دائن (حركة)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الرصيد النهائي</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($trial_balance_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['code']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($row['debit'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($row['credit'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?php echo $row['balance'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo number_format(abs($row['balance']), 2); ?>
                                    <span class="text-xs text-gray-500"><?php echo $row['balance'] >= 0 ? '(مدين)' : '(دائن)'; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td colspan="2" class="px-6 py-3 text-left text-gray-800">الإجمالي</td>
                            <td class="px-6 py-3 text-right text-gray-800"><?php echo number_format($total_debit, 2); ?></td>
                            <td class="px-6 py-3 text-right text-gray-800"><?php echo number_format($total_credit, 2); ?></td>
                            <td class="px-6 py-3 text-right">
                                <?php if(abs($total_debit - $total_credit) < 0.01): ?>
                                    <span class="px-3 py-1 text-xs text-green-800 bg-green-200 rounded-full">متوازن</span>
                                <?php else: ?>
                                     <span class="px-3 py-1 text-xs text-red-800 bg-red-200 rounded-full">غير متوازن</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
$page_specific_footer_scripts = '<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>';
include 'layouts/responsive_base.php';
?>