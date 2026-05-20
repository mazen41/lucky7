<?php
// /views/accounting/dashboard_view.php
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root { --mai-green: #10B981; --mai-green-dark: #059669; }
    body { background-color: #f5f5f5; }
    .nav-button {
        background-color: var(--mai-green); color: white; transition: background-color 0.2s ease-in-out;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        padding: 0.75rem 1rem; border-radius: 0.75rem; font-weight: 600; text-decoration: none;
    }
    .nav-button.active, .nav-button:hover { background-color: var(--mai-green-dark); }
    .nav-button-secondary { background-color: #6b7280; color: white; }
    .nav-button-secondary:hover { background-color: #4b5563; }
    .content-card {
        background-color: #ffffff; border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        padding: 1.5rem; height: 100%;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in-item { animation: fadeIn 0.5s ease-out forwards; }
    [x-cloak] { display: none !important; }
</style>

<div x-data="pageController()" class="container mx-auto p-4 md:p-6">

    <header class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">📊 لوحة التحكم المالي للصالون النسائي</h1>
    </header>

    <!-- Navigation -->
    <nav class="mb-8 flex flex-wrap justify-center gap-2">
        <a href="/admin/dashboard.php" class="nav-button nav-button-secondary"><i class="fas fa-arrow-left"></i> العودة للرئيسية</a>
        <a href="#" @click.prevent="activeTab = 'dashboard'" :class="{'active': activeTab === 'dashboard'}" class="nav-button"><i class="fas fa-chart-pie"></i> لوحة التحكم</a>
        <a href="#" @click.prevent="activeTab = 'profit_and_loss'" :class="{'active': activeTab === 'profit_and_loss'}" class="nav-button"><i class="fas fa-list-alt"></i> قائمة الأرباح</a>
        <a href="#" @click.prevent="activeTab = 'cash_flow'" :class="{'active': activeTab === 'cash_flow'}" class="nav-button"><i class="fas fa-exchange-alt"></i> حركة النقد</a>
        <a href="#" @click.prevent="activeTab = 'trial_balance'" :class="{'active': activeTab === 'trial_balance'}" class="nav-button"><i class="fas fa-balance-scale"></i> ميزان المراجعة</a>
        <a href="#" @click.prevent="activeTab = 'chart_of_accounts'" :class="{'active': activeTab === 'chart_of_accounts'}" class="nav-button"><i class="fas fa-sitemap"></i> شجرة الحسابات</a>
        <a href="#" @click.prevent="activeTab = 'settings'" :class="{'active': activeTab === 'settings'}" class="nav-button"><i class="fas fa-cogs"></i> الإعدادات</a>
        <a href="#" @click.prevent="activeTab = 'journal_entries'" :class="{'active': activeTab === 'journal_entries'}" class="nav-button"><i class="fas fa-book"></i> القيود اليومية</a>
    </nav>
    
    <!-- Date Filter Form -->
     <form @submit.prevent="applyFilter()" class="flex flex-col sm:flex-row items-center justify-center gap-4 text-sm mb-8 p-4 bg-white rounded-xl shadow-sm" x-show="['profit_and_loss', 'cash_flow', 'trial_balance', 'journal_entries'].includes(activeTab)">
        <div class="flex items-center gap-2"><label class="font-semibold">من:</label><input type="date" x-model="startDate" class="p-2 border rounded-lg"></div>
        <div class="flex items-center gap-2"><label class="font-semibold">إلى:</label><input type="date" x-model="endDate" class="p-2 border rounded-lg"></div>
        <button type="submit" class="nav-button"><i class="fas fa-filter"></i> تطبيق</button>
    </form>


    <!-- Tab Content -->
    <div>
        <!-- TAB: Dashboard -->
        <div x-show="activeTab === 'dashboard'" x-transition class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="content-card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-700">نسبة المصروفات</h2>
                        <select id="period-select" @change="applyPieFilter($event.target.value)" class="border-gray-300 rounded-lg shadow-sm text-sm">
                            <option value="all" <?= ($pie_month === 'all') ? 'selected' : '' ?>>هذه السنة</option>
                            <?php for ($i = 0; $i < 12; $i++): $month_val = date("Y-m", strtotime("-$i months")); $month_text = $arabic_months[(int)date("n", strtotime($month_val)) - 1] . " " . date("Y", strtotime($month_val)); ?>
                            <option value="<?= $month_val ?>" <?= ($pie_month === $month_val) ? 'selected' : '' ?>><?= $month_text ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="flex justify-center items-center h-[300px]"><canvas id="expensesPieChart"></canvas></div>
                </div>
                <div class="content-card">
                    <h2 class="text-xl font-bold text-gray-700 text-center mb-4">مقارنة الإيرادات والمصروفات والأرباح</h2>
                    <div class="h-[300px] flex justify-center items-center"><canvas id="comparisonLineChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- TAB: Profit and Loss -->
        <div x-show="activeTab === 'profit_and_loss'" x-cloak x-transition>
             <div class="content-card max-w-4xl mx-auto"><div class="text-center mb-6"><h2 class="text-2xl font-bold text-gray-800">قائمة الأرباح والخسائر للصالون النسائي</h2><p class="text-sm text-gray-500">الفترة: من <?= htmlspecialchars($start_date) ?> إلى <?= htmlspecialchars($end_date) ?></p></div><div class="space-y-6"><div><h3 class="text-xl font-bold text-mai-green border-b-2 border-mai-green pb-2 mb-3 fade-in-item">الإيرادات</h3><div class="space-y-2"><?php foreach($profit_and_loss_data['revenues'] as $index => $item): ?><div style="animation-delay: <?= $index * 100 ?>ms" class="fade-in-item flex justify-between items-center p-2 rounded-lg hover:bg-gray-50"><span class="text-gray-700"><?= htmlspecialchars($item['account_name_ar']) ?></span><span class="font-mono font-semibold text-gray-800"><?= number_format($item['balance'], 2) ?> ر.س</span></div><?php endforeach; ?></div><div class="fade-in-item flex justify-between items-center p-3 mt-3 bg-green-50 rounded-lg border-t-2 border-green-200"><span class="font-bold text-green-800">إجمالي الإيرادات</span><span class="font-mono font-bold text-lg text-green-800"><?= number_format($profit_and_loss_data['total_revenue'], 2) ?> ر.س</span></div></div><div><h3 class="text-xl font-bold text-red-500 border-b-2 border-red-500 pb-2 mb-3 fade-in-item" style="animation-delay: 500ms">المصروفات</h3><div class="space-y-2"><?php foreach($profit_and_loss_data['expenses'] as $index => $item): ?><div style="animation-delay: <?= 600 + ($index * 100) ?>ms" class="fade-in-item flex justify-between items-center p-2 rounded-lg hover:bg-gray-50"><span class="text-gray-700"><?= htmlspecialchars($item['account_name_ar']) ?></span><span class="font-mono font-semibold text-gray-800">(<?= number_format($item['balance'], 2) ?>) ر.س</span></div><?php endforeach; ?></div><div class="fade-in-item flex justify-between items-center p-3 mt-3 bg-red-50 rounded-lg border-t-2 border-red-200" style="animation-delay: 1s"><span class="font-bold text-red-800">إجمالي المصروفات</span><span class="font-mono font-bold text-lg text-red-800">(<?= number_format($profit_and_loss_data['total_expense'], 2) ?>) ر.س</span></div></div><div class="pt-6 border-t-2 border-dashed"><div class="fade-in-item flex justify-between items-center p-4 rounded-xl <?= $profit_and_loss_data['net_profit'] >= 0 ? 'bg-blue-600 text-white' : 'bg-red-600 text-white' ?>" style="animation-delay: 1.2s"><h3 class="text-2xl font-bold">صافي الربح</h3><span class="font-mono text-3xl font-bold"><?= number_format($profit_and_loss_data['net_profit'], 2) ?> ر.س</span></div></div></div></div>
        </div>
        
		<!-- TAB: Cash Flow -->
        <div x-show="activeTab === 'cash_flow'" x-cloak x-transition>
            <div class="content-card max-w-4xl mx-auto"><div class="p-4 sm:p-6 border-b"><h2 class="text-2xl font-bold text-center text-gray-800">تقرير حركة النقدية والبنوك</h2><p class="text-center text-sm text-gray-500">الفترة من <?= htmlspecialchars($start_date) ?> إلى <?= htmlspecialchars($end_date) ?></p></div><div class="p-6"><div class="bg-gray-100 rounded-lg p-4 mb-6"><div class="flex justify-between items-center"><div><h3 class="text-lg font-bold text-gray-700">الرصيد الافتتاحي</h3><p class="text-xs text-gray-500">رصيد ما قبل <?= $start_date ?></p></div><span class="font-mono text-2xl font-bold text-gray-800"><?= number_format($cash_flow_data['opening_balance'], 2) ?></span></div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6"><div class="bg-green-50 rounded-lg p-4 border border-green-200"><div class="flex justify-between items-center mb-2"><span class="font-semibold text-green-800"><i class="fas fa-arrow-down mr-2"></i> إجمالي المقبوضات</span></div><div class="text-right"><span class="font-mono text-xl font-bold text-green-700">+ <?= number_format($cash_flow_data['total_in'], 2) ?></span></div></div><div class="bg-red-50 rounded-lg p-4 border border-red-200"><div class="flex justify-between items-center mb-2"><span class="font-semibold text-red-800"><i class="fas fa-arrow-up mr-2"></i> إجمالي المدفوعات</span></div><div class="text-right"><span class="font-mono text-xl font-bold text-red-700">- <?= number_format($cash_flow_data['total_out'], 2) ?></span></div></div></div><div class="border-t border-b py-3 mb-6 flex justify-between items-center"><span class="font-semibold">صافي حركة الفترة</span><span class="font-mono font-bold text-lg <?= $cash_flow_data['net_movement'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= number_format($cash_flow_data['net_movement'], 2) ?></span></div><div class="bg-blue-600 text-white rounded-lg p-6"><div class="flex justify-between items-center"><div><h3 class="text-xl font-bold">الرصيد النهائي الحالي</h3><p class="text-blue-200 text-sm">بتاريخ <?= $end_date ?></p></div><span class="font-mono text-3xl font-bold"><?= number_format($cash_flow_data['closing_balance'], 2) ?></span></div></div></div></div>
        </div>

        <!-- TAB: Trial Balance -->
        <div x-show="activeTab === 'trial_balance'" x-cloak x-transition>
            <div class="content-card overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-right">الحساب</th><th class="px-4 py-3 text-center">مدين</th><th class="px-4 py-3 text-center">دائن</th><th class="px-4 py-3 text-center">الرصيد</th></tr></thead><tbody class="divide-y"><?php $grand_total_debit = 0; $grand_total_credit = 0; foreach ($trial_balance_data as $row): $total_debit = (float)$row['total_debit']; $total_credit = (float)$row['total_credit']; $grand_total_debit += $total_debit; $grand_total_credit += $total_credit; $balance = ($row['normal_balance'] === 'debit') ? $total_debit - $total_credit : $total_credit - $total_debit; ?><tr><td class="px-4 py-4"><div class="font-medium"><?= htmlspecialchars($row['account_name_ar']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($row['account_code']) ?></div></td><td class="px-4 py-4 text-center font-mono"><?= number_format($total_debit, 2) ?></td><td class="px-4 py-4 text-center font-mono"><?= number_format($total_credit, 2) ?></td><td class="px-4 py-4 text-center font-bold font-mono <?= $balance >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= number_format(abs($balance), 2) ?> <span class="text-xs font-normal"><?= $balance >= 0 ? ($row['normal_balance'] === 'debit' ? 'مدين' : 'دائن') : 'عكسي' ?></span></td></tr><?php endforeach; ?></tbody><tfoot class="bg-gray-100"><tr><td class="px-4 py-4 font-bold text-lg">الإجمالي</td><td class="px-4 py-4 text-center font-bold text-lg font-mono"><?= number_format($grand_total_debit, 2) ?></td><td class="px-4 py-4 text-center font-bold text-lg font-mono"><?= number_format($grand_total_credit, 2) ?></td><td class="px-4 py-4 text-center"><?php if (abs($grand_total_debit - $grand_total_credit) < 0.01): ?><span class="px-3 py-1 text-sm rounded-full font-semibold bg-green-100 text-green-800">متوازن</span><?php else: ?><span class="px-3 py-1 text-sm rounded-full font-semibold bg-red-100 text-red-800">غير متوازن</span><?php endif; ?></td></tr></tfoot></table></div>
        </div>
        
        <!-- TAB: Chart of Accounts -->
        <div x-show="activeTab === 'chart_of_accounts'" x-cloak x-transition>
            <div class="content-card"><div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">شجرة الحسابات</h2><button @click="$dispatch('open-modal', { id: 'account-modal' })" class="nav-button"><i class="fas fa-plus"></i> إضافة حساب</button></div><div class="overflow-x-auto"><table class="min-w-full divide-y"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-right">الحساب</th><th class="px-6 py-3 text-right">النوع</th><th class="px-6 py-3 text-center">الإجراءات</th></tr></thead><tbody class="divide-y"><?php foreach ($all_accounts_for_coa_tab as $account): ?><tr><td class="px-6 py-4"><div class="font-medium"><?= htmlspecialchars($account['account_name_ar']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($account['account_code']) ?></div></td><td class="px-6 py-4"><?= htmlspecialchars($account['type_name_ar']) ?></td><td class="px-6 py-4 text-center space-x-2"><button @click="$dispatch('open-modal', { id: 'account-modal', isEdit: true, account: <?= htmlspecialchars(json_encode($account)) ?> })" class="text-indigo-600 hover:text-indigo-900">تعديل</button><form method="POST" action="AccountingController.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟')" class="inline"><input type="hidden" name="action" value="delete_account"><input type="hidden" name="account_id" value="<?= $account['id'] ?>"><input type="hidden" name="active_tab" value="chart_of_accounts"><button type="submit" class="text-red-600 hover:text-red-900">حذف</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
        </div>

        <!-- TAB: Settings -->
        <div x-show="activeTab === 'settings'" x-cloak x-transition>
            <form method="POST" action="AccountingController.php"><input type="hidden" name="active_tab" value="settings"><input type="hidden" name="action" value="save_settings"><div class="content-card grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6"><h2 class="text-2xl font-bold col-span-1 md:col-span-2">إعدادات ربط الحسابات</h2><?php foreach ($settings_fields as $key => $field): ?><div class="flex items-start gap-3"><i class="fas <?= $field['icon'] ?> text-mai-green text-xl mt-1"></i><div class="w-full"><label for="<?= $key ?>" class="block text-sm font-medium mb-1"><?= $field['label'] ?></label><select id="<?= $key ?>" name="settings[<?= $key ?>]" class="w-full p-2 border rounded-lg bg-gray-50" required><option value="">-- اختر حساب --</option><?php foreach ($accounts_list as $account): ?><option value="<?= $account['id'] ?>" <?php if (isset($current_settings[$key]) && $current_settings[$key] == $account['id']) echo ' selected'; ?>>[<?= htmlspecialchars($account['account_code']) ?>] <?= htmlspecialchars($account['account_name_ar']) ?></option><?php endforeach; ?></select></div></div><?php endforeach; ?></div><div class="mt-8 text-left"><button type="submit" class="nav-button"><i class="fas fa-save mr-2"></i>حفظ الإعدادات</button></div></form>
        </div>
        
        <!-- TAB: Journal Entries -->
        <div x-show="activeTab === 'journal_entries'" x-cloak x-transition>
            <div class="content-card" x-data="{ showAddForm: false }">
                <div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">القيود اليومية</h2><button @click="showAddForm = !showAddForm" class="nav-button"><i class="fas fa-plus"></i> إضافة قيد يدوي</button></div>
                <div x-show="showAddForm" x-transition class="mb-8 p-4 border rounded-lg bg-gray-50"><form method="POST" action="AccountingController.php" x-data="journalEntryManager()"><input type="hidden" name="action" value="add_manual_entry"><input type="hidden" name="active_tab" value="journal_entries"><div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4"><div><label>تاريخ القيد</label><input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required class="mt-1 w-full p-2 border rounded-lg"></div><div><label>الوصف</label><input type="text" name="description" placeholder="وصف القيد" required class="mt-1 w-full p-2 border rounded-lg"></div></div><div class="overflow-x-auto border rounded-lg"><table class="min-w-full divide-y"><thead class="bg-gray-200"><tr><th class="p-2 text-right">الحساب</th><th class="p-2 text-right">مدين</th><th class="p-2 text-right">دائن</th><th></th></tr></thead><tbody><template x-for="(row, index) in rows" :key="index"><tr class="bg-white"><td class="p-2 w-1/2"><select :name="`account_id[${index}]`" class="w-full p-2 border rounded" required><option value="">-- اختر --</option><?php foreach ($accounts_list as $account): ?><option value="<?= $account['id'] ?>">[<?= $account['account_code'] ?>] <?= htmlspecialchars($account['account_name_ar']) ?></option><?php endforeach; ?></select></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`debit[${index}]`" x-model.number="row.debit" @input="validateRow(index, 'debit')" class="w-full p-2 border rounded" placeholder="0.00"></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`credit[${index}]`" x-model.number="row.credit" @input="validateRow(index, 'credit')" class="w-full p-2 border rounded" placeholder="0.00"></td><td class="p-2 text-center"><button type="button" @click="removeRow(index)" x-show="rows.length > 2" class="text-red-500">&times;</button></td></tr></template></tbody><tfoot class="bg-gray-100"><tr><td class="p-2 font-bold text-left">الإجمالي</td><td class="p-2 font-bold font-mono" x-text="totals.debit.toFixed(2)"></td><td class="p-2 font-bold font-mono" x-text="totals.credit.toFixed(2)"></td><td></td></tr></tfoot></table></div><div class="mt-4 flex justify-between items-center"><button type="button" @click="addRow()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"><i class="fas fa-plus"></i> إضافة سطر</button><div><div x-show="!isBalanced()" class="text-red-600 font-semibold inline mr-4">القيد غير متوازن!</div><button type="submit" :disabled="!isBalanced() || totals.debit === 0" class="nav-button"><i class="fas fa-save"></i> حفظ القيد</button></div></div></form></div>
                
                <!-- Journal Entries Table -->
                <div class="overflow-x-auto"><table class="min-w-full divide-y"><thead class="bg-gray-50"><tr><th class="p-3 text-right">التاريخ</th><th class="p-3 text-right">الرقم</th><th class="p-3 text-right">الوصف</th><th class="p-3 text-right">مدين</th><th class="p-3 text-right">دائن</th><th class="p-3 text-center">الإجراءات</th></tr></thead><tbody class="divide-y"><?php foreach ($journal_entries as $entry): ?><tr><td class="p-3"><?= $entry['entry_date'] ?></td><td class="p-3">#<?= $entry['id'] ?></td><td class="p-3"><?= htmlspecialchars($entry['description']) ?></td><td class="p-3 font-mono"><?= number_format($entry['total_debit'], 2) ?></td><td class="p-3 font-mono"><?= number_format($entry['total_credit'], 2) ?></td>
                <td class="p-3 text-center space-x-2">
                    <button @click="$dispatch('open-edit-modal', { id: <?= $entry['id'] ?> })" class="text-indigo-600 hover:text-indigo-900 text-sm">تعديل</button>
                    <form method="POST" action="AccountingController.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا القيد؟ لا يمكن التراجع عن هذا الإجراء.')" class="inline">
                        <input type="hidden" name="action" value="delete_journal_entry">
                        <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                        <input type="hidden" name="active_tab" value="journal_entries">
                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm">حذف</button>
                    </form>
                </td>
                </tr><?php endforeach; ?></tbody></table></div>
                
                <!-- =========== START: EDITED PAGINATION BLOCK =========== -->
                <?php
                $currentPage = isset($page) ? (int)$page : 1;
                $totalPages = isset($total_pages) ? (int)$total_pages : 1;
                $window = 2; // How many pages to show around the current page
                
                $baseUrl = "?tab=journal_entries&start_date=" . htmlspecialchars($start_date) . "&end_date=" . htmlspecialchars($end_date);

                if ($totalPages > 1):
                ?>
                <nav class="mt-6 flex justify-center items-center gap-2 text-sm font-medium" aria-label="Pagination">
                    <!-- Previous Button -->
                    <a href="<?= $currentPage > 1 ? $baseUrl . '&page=' . ($currentPage - 1) : '#' ?>"
                       class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 <?= $currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <i class="fas fa-chevron-right mr-1 text-xs"></i> السابق
                    </a>

                    <div class="flex items-center gap-2">
                    <?php
                        // Function to create a page link
                        function renderPageLink($i, $currentPage, $baseUrl) {
                            if ($i == $currentPage) {
                                return '<span aria-current="page" class="flex items-center justify-center px-3 h-8 text-white border border-mai-green bg-mai-green rounded-lg">' . $i . '</span>';
                            } else {
                                return '<a href="' . $baseUrl . '&page=' . $i . '" class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700">' . $i . '</a>';
                            }
                        }
                        
                        // Render first page
                        echo renderPageLink(1, $currentPage, $baseUrl);

                        // Render ellipsis if needed
                        if ($currentPage > $window + 2) {
                            echo '<span class="flex items-center justify-center px-3 h-8">...</span>';
                        }

                        // Render pages in the window
                        for ($i = max(2, $currentPage - $window); $i <= min($totalPages - 1, $currentPage + $window); $i++) {
                            echo renderPageLink($i, $currentPage, $baseUrl);
                        }

                        // Render ellipsis if needed
                        if ($currentPage < $totalPages - $window - 1) {
                            echo '<span class="flex items-center justify-center px-3 h-8">...</span>';
                        }
                        
                        // Render last page
                        if ($totalPages > 1) {
                            echo renderPageLink($totalPages, $currentPage, $baseUrl);
                        }
                    ?>
                    </div>

                    <!-- Next Button -->
                    <a href="<?= $currentPage < $totalPages ? $baseUrl . '&page=' . ($currentPage + 1) : '#' ?>"
                       class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700 <?= $currentPage >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        التالي <i class="fas fa-chevron-left ml-1 text-xs"></i>
                    </a>
                </nav>
                <?php endif; ?>
                <!-- =========== END: EDITED PAGINATION BLOCK =========== -->

            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<div x-data="accountModal()" @open-modal.window="openModal($event.detail)" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" x-cloak><div class="flex items-center justify-center min-h-screen"><div x-show="show" class="fixed inset-0 transition-opacity" @click="show = false"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div><div x-show="show" class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full"><form method="POST" action="AccountingController.php"><input type="hidden" name="active_tab" value="chart_of_accounts"><input type="hidden" name="action" :value="isEdit ? 'edit_account' : 'add_account'"><input type="hidden" name="account_id" :value="account.id"><div class="px-6 py-4"><h3 class="text-lg font-medium" x-text="isEdit ? 'تعديل حساب' : 'إضافة حساب جديد'"></h3><div class="mt-4 space-y-4"><div><label>كود الحساب</label><input type="text" name="account_code" x-model="account.account_code" required class="mt-1 w-full p-2 border rounded"></div><div><label>اسم الحساب (عربي)</label><input type="text" name="account_name_ar" x-model="account.account_name_ar" required class="mt-1 w-full p-2 border rounded"></div><div><label>اسم الحساب (إنجليزي)</label><input type="text" name="account_name_en" x-model="account.account_name_en" class="mt-1 w-full p-2 border rounded"></div><div><label>نوع الحساب</label><select name="account_type_id" x-model="account.account_type_id" required class="mt-1 w-full p-2 border rounded"><option value="">-- اختر --</option><?php foreach ($account_types as $type): ?><option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name_ar']) ?></option><?php endforeach; ?></select></div></div></div><div class="bg-gray-50 px-6 py-3 flex flex-row-reverse"><button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-md px-4 py-2 bg-mai-green text-white hover:bg-mai-green-dark" x-text="isEdit ? 'حفظ التعديلات' : 'إنشاء'"></button><button type="button" @click="show = false" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md px-4 py-2 bg-white text-gray-700 border hover:bg-gray-50 ml-3">إلغاء</button></div></form></div></div></div>

<!-- NEW: Edit Journal Entry Modal -->
<div x-data="editJournalEntryManager()" @open-edit-modal.window="openModal($event.detail.id)" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
    <div class="flex items-center justify-center min-h-screen p-4"><div x-show="show" class="fixed inset-0 transition-opacity" @click="show = false"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
    <div x-show="show" class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-4xl sm:w-full">
        <form method="POST" action="AccountingController.php" @submit="isSubmitting = true">
            <input type="hidden" name="action" value="edit_journal_entry">
            <input type="hidden" name="active_tab" value="journal_entries">
            <input type="hidden" name="entry_id" :value="entry.id">
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium">تعديل القيد اليومي #<span x-text="entry.id"></span></h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label>تاريخ القيد</label><input type="date" name="entry_date" x-model="entry.entry_date" required class="mt-1 w-full p-2 border rounded-lg"></div>
                    <div><label>الوصف</label><input type="text" name="description" x-model="entry.description" required class="mt-1 w-full p-2 border rounded-lg"></div>
                </div>
                <div class="mt-4 overflow-x-auto border rounded-lg">
                    <table class="min-w-full divide-y"><thead class="bg-gray-200"><tr><th class="p-2 text-right">الحساب</th><th class="p-2 text-right">مدين</th><th class="p-2 text-right">دائن</th><th></th></tr></thead>
                    <tbody>
                        <template x-if="isLoading"><tr><td colspan="4" class="text-center p-4">جاري تحميل البيانات...</td></tr></template>
                        <template x-for="(row, index) in rows" :key="index"><tr class="bg-white"><td class="p-2 w-1/2"><select :name="`account_id[${index}]`" x-model="row.account_id" class="w-full p-2 border rounded" required><option value="">-- اختر --</option><?php foreach ($accounts_list as $account): ?><option value="<?= $account['id'] ?>">[<?= $account['account_code'] ?>] <?= htmlspecialchars($account['account_name_ar']) ?></option><?php endforeach; ?></select></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`debit[${index}]`" x-model.number="row.debit" @input="validateRow(index, 'debit')" class="w-full p-2 border rounded" placeholder="0.00"></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`credit[${index}]`" x-model.number="row.credit" @input="validateRow(index, 'credit')" class="w-full p-2 border rounded" placeholder="0.00"></td><td class="p-2 text-center"><button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="text-red-500">&times;</button></td></tr></template>
                    </tbody>
                    <tfoot class="bg-gray-100"><tr><td class="p-2 font-bold text-left">الإجمالي</td><td class="p-2 font-bold font-mono" x-text="totals.debit.toFixed(2)"></td><td class="p-2 font-bold font-mono" x-text="totals.credit.toFixed(2)"></td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-between items-center">
                <button type="button" @click="addRow()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"><i class="fas fa-plus"></i> إضافة سطر</button>
                <div class="flex items-center">
                    <div x-show="!isBalanced()" class="text-red-600 font-semibold inline mr-4">القيد غير متوازن!</div>
                    <button type="button" @click="show = false" class="sm:w-auto inline-flex justify-center rounded-md px-4 py-2 bg-white text-gray-700 border hover:bg-gray-50 ml-3">إلغاء</button>
                    <button type="submit" :disabled="!isBalanced() || totals.debit === 0 || isSubmitting" class="w-full sm:w-auto inline-flex justify-center rounded-md px-4 py-2 bg-mai-green text-white hover:bg-mai-green-dark" x-text="isSubmitting ? 'جاري الحفظ...' : 'حفظ التعديلات'"></button>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pageController', () => ({
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'dashboard',
        startDate: '<?= htmlspecialchars($start_date); ?>',
        endDate: '<?= htmlspecialchars($end_date); ?>',
        applyFilter() {
            let url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('start_date', this.startDate);
            url.searchParams.set('end_date', this.endDate);
            url.searchParams.set('tab', this.activeTab);
            window.location.href = url.toString();
        },
        applyPieFilter(month) {
            let url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('tab', 'dashboard');
            url.searchParams.set('pie_month', month);
            window.location.href = url.toString();
        }
    }));
    
    // For ADD form
    Alpine.data('journalEntryManager', () => ({
        rows: [{ debit: '', credit: '' }, { debit: '', credit: '' }],
        addRow() { this.rows.push({ debit: '', credit: '' }); },
        removeRow(index) { if (this.rows.length > 2) this.rows.splice(index, 1); },
        validateRow(index, field) { 
            if (field === 'debit' && this.rows[index].debit > 0) this.rows[index].credit = ''; 
            else if (field === 'credit' && this.rows[index].credit > 0) this.rows[index].debit = ''; 
        },
        get totals() { return this.rows.reduce((acc, row) => { acc.debit += parseFloat(row.debit || 0); acc.credit += parseFloat(row.credit || 0); return acc; }, { debit: 0, credit: 0 }); },
        isBalanced() { return Math.abs(this.totals.debit - this.totals.credit) < 0.01; }
    }));

    Alpine.data('accountModal', () => ({
        show: false, isEdit: false, account: {},
        openModal(detail) {
            this.isEdit = detail.isEdit || false;
            this.account = this.isEdit ? { ...detail.account } : { id: '', account_code: '', account_name_ar: '', account_name_en: '', account_type_id: '' };
            this.show = true;
        }
    }));

    // NEW: For EDIT modal
    Alpine.data('editJournalEntryManager', () => ({
        show: false,
        isLoading: true,
        isSubmitting: false,
        entry: { id: null, entry_date: '', description: '' },
        rows: [],
        async openModal(entryId) {
            this.show = true;
            this.isLoading = true;
            this.isSubmitting = false;
            this.rows = [];
            try {
                const response = await fetch(`?action=get_entry_details&id=${entryId}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                
                this.entry = {
                    id: result.data.id,
                    entry_date: result.data.entry_date,
                    description: result.data.description
                };

                this.rows = result.data.items.map(item => ({
                    account_id: item.account_id,
                    debit: item.type === 'debit' ? parseFloat(item.amount) : '',
                    credit: item.type === 'credit' ? parseFloat(item.amount) : ''
                }));

                if (this.rows.length === 0) { // Ensure at least one row exists
                    this.addRow();
                }

            } catch (error) {
                console.error('Failed to fetch entry details:', error);
                alert('فشل في جلب تفاصيل القيد.');  
                this.show = false;
            } finally {
                this.isLoading = false;
            }
        },
        addRow() { this.rows.push({ account_id: '', debit: '', credit: '' }); },
        removeRow(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
        validateRow(index, field) { 
            if (field === 'debit' && this.rows[index].debit > 0) this.rows[index].credit = ''; 
            else if (field === 'credit' && this.rows[index].credit > 0) this.rows[index].debit = ''; 
        },
        get totals() { return this.rows.reduce((acc, row) => { acc.debit += parseFloat(row.debit || 0); acc.credit += parseFloat(row.credit || 0); return acc; }, { debit: 0, credit: 0 }); },
        isBalanced() { return Math.abs(this.totals.debit - this.totals.credit) < 0.01; }
    }));
});

document.addEventListener('DOMContentLoaded', function () {
    const lineCtx = document.getElementById('comparisonLineChart')?.getContext('2d');
    if (lineCtx) { new Chart(lineCtx, { type: 'line', data: { labels: <?= json_encode($chart_labels) ?>, datasets: [ { label: "الإيرادات", data: <?= json_encode($chart_revenues) ?>, borderColor: "#3b82f6", backgroundColor: "rgba(59, 130, 246, 0.1)", tension: 0.4, fill: true, pointBackgroundColor: "#3b82f6" }, { label: "المصروفات", data: <?= json_encode($chart_expenses) ?>, borderColor: "#ec4899", backgroundColor: "rgba(236, 72, 153, 0.1)", tension: 0.4, fill: true, pointBackgroundColor: "#ec4899" }, { label: "الأرباح", data: <?= json_encode($chart_profits) ?>, borderColor: "#f97316", tension: 0.4, borderDash: [5, 5], fill: false, pointBackgroundColor: "#f97316" } ] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } }); }
    const pieCtx = document.getElementById('expensesPieChart')?.getContext('2d');
    const pieData = <?= json_encode($pie_chart_data ?? []) ?>;
    if (pieCtx && pieData.length > 0) { new Chart(pieCtx, { type: 'pie', data: { labels: <?= json_encode($pie_chart_labels ?? []) ?>, datasets: [{ data: pieData, backgroundColor: ['#3b82f6', '#ec4899', '#f97316', '#facc15', '#10b981'], borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); } else if (pieCtx) { pieCtx.textAlign = 'center'; pieCtx.textBaseline = 'middle'; pieCtx.font = '16px sans-serif'; pieCtx.fillText('لا توجد بيانات مصروفات لعرضها', pieCtx.canvas.width / 2, pieCtx.canvas.height / 2); }
});
</script>