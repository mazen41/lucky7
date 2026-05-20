<?php
// CRITICAL FIX: These lines will display any PHP errors instead of showing a white page.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'auth.php';
require_once '../config/database.php';
require_once 'reports_engine.php';
require_once 'users_functions.php';

checkAdminAuth();

// --- PERMISSION CHECK: Page Access ---
requirePermission('reports.access');

$database = new Database(); $db = $database->getConnection(); $reports = new ReportsEngine($db);

// --- PERMISSION CHECK: Filter Reports for Frontend Display ---
$all_reports = [
    // --- ACCOUNTING REPORTS ---
    ['id' => 'profit_and_loss', 'title' => 'قائمة الأرباح والخسائر', 'description' => 'عرض تفصيلي للإيرادات والمصروفات وصافي الربح.', 'icon' => 'fas fa-list-alt', 'category' => 'محاسبي', 'color' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'], 'filters' => []],
    ['id' => 'cash_flow', 'title' => 'حركة النقد والبنوك', 'description' => 'ملخص حركة النقدية مع الأرصدة الافتتاحية والنهائية.', 'icon' => 'fas fa-exchange-alt', 'category' => 'محاسبي', 'color' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'], 'filters' => []],
    ['id' => 'trial_balance', 'title' => 'ميزان المراجعة', 'description' => 'عرض أرصدة جميع الحسابات (مدين ودائن).', 'icon' => 'fas fa-balance-scale', 'category' => 'محاسبي', 'color' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'], 'filters' => []],
    
    // --- FINANCIAL & SALES REPORTS ---
    ['id' => 'invoices', 'title' => 'تقرير الفواتير', 'description' => 'تحليل الفواتير والإيرادات مع تفاصيل الضريبة', 'icon' => 'fas fa-file-invoice-dollar', 'category' => 'مالي', 'color' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'], 'filters' => ['payment_method', 'user']],
    ['id' => 'sales_by_item', 'title' => 'تقرير المبيعات حسب الصنف', 'description' => 'تحليل مبيعات كل خدمة ومنتج (الأكثر مبيعاً).', 'icon' => 'fas fa-tags', 'category' => 'مالي', 'color' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-600'], 'filters' => []],
    ['id' => 'sales_by_payment', 'title' => 'المبيعات حسب نوع الدفع', 'description' => 'ملخص إجمالي المبيعات لكل طريقة دفع', 'icon' => 'fas fa-credit-card', 'category' => 'مالي', 'color' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'], 'filters' => []],
    ['id' => 'expenses', 'title' => 'تقرير المصروفات', 'description' => 'عرض جميع المصروفات المسجلة', 'icon' => 'fas fa-wallet', 'category' => 'مالي', 'color' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600'], 'filters' => []],
    ['id' => 'net_profit', 'title' => 'تقرير الربح الصافي', 'description' => 'حساب صافي الربح (المبيعات - المصروفات)', 'icon' => 'fas fa-piggy-bank', 'category' => 'مالي', 'color' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-600'], 'filters' => []],
    ['id' => 'returns', 'title' => 'تقرير المرتجعات', 'description' => 'عرض المبالغ المسترجعة وأسبابها', 'icon' => 'fas fa-undo', 'category' => 'مالي', 'color' => ['bg' => 'bg-red-100', 'text' => 'text-red-600'], 'filters' => []],
    
    // --- OTHER REPORTS ---
    ['id' => 'inventory', 'title' => 'تقرير المخزون', 'description' => 'جرد المنتجات وحالة المخزون', 'icon' => 'fas fa-boxes', 'category' => 'المخزون', 'color' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-600'], 'filters' => []],
    ['id' => 'appointments', 'title' => 'تقرير المواعيد', 'description' => 'تحليل حالات المواعيد والحضور', 'icon' => 'fas fa-calendar-check', 'category' => 'العمليات', 'color' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'], 'filters' => ['specialist']],
    ['id' => 'customers', 'title' => 'تقرير العملاء', 'description' => 'نظرة عامة على إنفاق وزيارات العملاء', 'icon' => 'fas fa-users', 'category' => 'العملاء', 'color' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'], 'filters' => []],
    ['id' => 'hr', 'title' => 'أداء الأخصائيات', 'description' => 'إيرادات وعدد خدمات كل أخصائية', 'icon' => 'fas fa-user-tie', 'category' => 'الموظفين', 'color' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600'], 'filters' => ['specialist']],
    ['id' => 'user_attendance', 'title' => 'تقرير حضور الموظفين', 'description' => 'عرض تفاصيل الحضور والغياب للموظفين', 'icon' => 'fas fa-user-clock', 'category' => 'الموظفين', 'color' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'], 'filters' => ['user']],
    ['id' => 'shifts', 'title' => 'تقرير الورديات', 'description' => 'ملخص الورديات والفروقات النقدية', 'icon' => 'fas fa-clock', 'category' => 'العمليات', 'color' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-600'], 'filters' => ['user']]
];
$report_titles_map = array_column($all_reports, 'title', 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $report_type = $_POST['report_type'];
    $filters = $_POST['filters'] ?? [];
    $export_format = $_POST['export_format'] ?? 'view';

    try {
        $permission_map = [
            'invoices' => 'reports.invoices', 'sales_by_payment' => 'reports.sales_by_payment', 'expenses' => 'reports.expenses',
            'net_profit' => 'reports.net_profit', 'returns' => 'reports.returns', 'inventory' => 'reports.inventory',
            'appointments' => 'reports.appointments', 'customers' => 'reports.customers', 'hr' => 'reports.hr',
            'user_attendance' => 'reports.user_attendance', 'shifts' => 'reports.shifts',
            'profit_and_loss' => 'reports.profit_and_loss', 'cash_flow' => 'reports.cash_flow', 'trial_balance' => 'reports.trial_balance'
        ];
        
        if (isset($permission_map[$report_type]) && !hasPermission($permission_map[$report_type])) {
            throw new Exception('ليس لديك الصلاحية لتوليد هذا النوع من التقارير.');
        }

        $result = [];
        switch ($report_type) {
            case 'profit_and_loss': $result = $reports->getProfitAndLossReport($filters); break;
            case 'cash_flow': $result = $reports->getCashFlowReport($filters); break;
            case 'trial_balance': $result = $reports->getTrialBalanceReport($filters); break;
            case 'invoices': $result = $reports->getInvoicesReport($filters); break;
            case 'sales_by_item': $result = $reports->getSalesByItemReport($filters); break;
            case 'sales_by_payment': $result = $reports->getSalesByPaymentMethodReport($filters); break;
            case 'expenses': $result = $reports->getExpensesReport($filters); break;
            case 'net_profit': $result = $reports->getNetProfitReport($filters); break;
            case 'returns': $result = $reports->getReturnsReport($filters); break;
            case 'inventory': $result = $reports->getInventoryReport($filters); break;
            case 'appointments': $result = $reports->getAppointmentsReport($filters); break;
            case 'customers': $result = $reports->getCustomersReport($filters); break;
            case 'hr': $result = $reports->getHRReport($filters); break;
            case 'user_attendance': $result = $reports->getUserAttendanceReport($filters); break;
            case 'shifts': $result = $reports->getShiftsReport($filters); break;
            default: throw new Exception('نوع تقرير غير صالح.');
        }
        
        $translated_data = $reports->translateDataKeys($result['data'] ?? []);
        
        if ($export_format === 'csv') {
            $reports->exportToCSV($translated_data, $report_type . '_' . date('Y-m-d'));
            exit;
        }
        if ($export_format === 'pdf') {
            $report_title = $report_titles_map[$report_type] ?? 'تقرير';
            $reports->exportToPDF($translated_data, $result['summary'] ?? [], $report_title, $report_type . '_' . date('Y-m-d'));
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $translated_data, 'summary' => $result['summary'] ?? []]);

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$users = $db->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$available_reports = [];
foreach ($all_reports as $report) {
    if ($report['id'] === 'sales_by_item' || hasPermission('reports.' . $report['id'])) { 
        $available_reports[] = $report; 
    }
}

$page_title = 'التقارير التحليلية';
ob_start();
?>
<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white text-xl"><i class="fas fa-chart-pie"></i></div>
            <div>
                <h1 class="text-2xl font-bold text-mai-dark">التقارير التحليلية</h1>
                <p class="text-sm text-gray-500">استخراج بيانات مفصلة حول جميع جوانب عمليات الصالون.</p>
            </div>
        </div>
    </div>
</div>

<!-- Alpine.js Component for Modal and Reports -->
<div x-data="reportsPage(<?= htmlspecialchars(json_encode($available_reports)) ?>)">
    <!-- Reports Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <template x-for="report in reports" :key="report.id">
            <div @click="openReportModal(report)" class="report-card bg-white rounded-xl shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center justify-between mb-3">
                    <div :class="report.color.bg" class="w-12 h-12 rounded-lg flex items-center justify-center">
                        <i :class="[report.icon, report.color.text]" class="text-2xl"></i>
                    </div>
                    <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-full" x-text="report.category"></span>
                </div>
                <h3 class="text-lg font-bold text-mai-dark mb-1" x-text="report.title"></h3>
                <p class="text-sm text-gray-500" x-text="report.description"></p>
            </div>
        </template>
        <template x-if="reports.length === 0">
            <div class="col-span-full bg-white rounded-xl shadow-md p-12 text-center text-gray-500">
                <i class="fas fa-lock text-5xl text-gray-300"></i>
                <p class="mt-4 font-bold">لا توجد تقارير متاحة</p>
                <p>ليس لديك الصلاحيات اللازمة لعرض أي من التقارير.</p>
            </div>
        </template>
    </div>

    <!-- Report Modal -->
    <div x-show="isModalOpen" x-cloak @keydown.escape.window="isModalOpen = false" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div @click.away="isModalOpen = false" class="bg-white rounded-xl w-full max-w-6xl max-h-[95vh] flex flex-col" x-show="isModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            <div class="p-5 border-b flex justify-between items-center flex-shrink-0">
                <h3 class="text-xl font-bold text-mai-dark" x-text="currentReport.title"></h3>
                <button @click="isModalOpen = false" class="text-gray-400 hover:text-red-500"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 bg-gray-50 border-b flex-shrink-0">
                <form id="reportFilters" class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                    <div><label class="text-sm font-medium text-gray-700">من تاريخ</label><input type="date" name="filters[date_from]" class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                    <div><label class="text-sm font-medium text-gray-700">إلى تاريخ</label><input type="date" name="filters[date_to]" class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                    <div x-show="currentReport.filters.includes('payment_method')"><label class="text-sm font-medium">طريقة الدفع</label><select name="filters[payment_method]" class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"><option value="">الكل</option><option value="cash">نقدي</option><option value="card">بطاقة</option><option value="bank_transfer">تحويل</option></select></div>
                    <div x-show="currentReport.filters.includes('user')"><label class="text-sm font-medium">الموظف/المستخدم</label><select name="filters[user_id]" class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"><option value="">الكل</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option><?php endforeach; ?></select></div>
                    <div x-show="currentReport.filters.includes('specialist')"><label class="text-sm font-medium">الأخصائية</label><select name="filters[specialist_id]" class="w-full mt-1 p-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"><option value="">الكل</option><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option><?php endforeach; ?></select></div>
                </form>
                <div class="flex gap-3 mt-4">
                    <button @click="generateReport('view')" :disabled="isLoading" class="bg-mai-brown text-white px-5 py-2 rounded-lg hover:bg-mai-dark-brown disabled:opacity-50 flex items-center"><i class="fas fa-eye mr-2"></i><span x-text="isLoading ? 'جاري العرض...' : 'عرض التقرير'"></span></button>
                    <button @click="generateReport('csv')" :disabled="isLoading" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50"><i class="fas fa-file-excel mr-2"></i>تصدير Excel</button>
                    <button @click="generateReport('pdf')" :disabled="isLoading" class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50"><i class="fas fa-file-pdf mr-2"></i>تصدير PDF</button>
                    <button 
                        @click="printSummaryReceipt()" 
                        x-show="(currentReport.id === 'invoices' || currentReport.id === 'sales_by_item') && (Object.keys(currentSummary).length > 0 || currentData.length > 0)"
                        x-cloak
                        class="bg-gray-700 text-white px-5 py-2 rounded-lg hover:bg-gray-800 disabled:opacity-50">
                        <i class="fas fa-receipt mr-2"></i>طباعة ملخص POS
                    </button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto">
                <div x-show="isLoading" class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-mai-brown"></i><p class="mt-3 text-gray-600">جاري تحميل البيانات...</p></div>
                <div x-show="!isLoading" x-html="reportHtml"></div>
            </div>
        </div>
    </div>
</div>

<iframe id="print-frame" style="display: none;"></iframe>

<script>
function reportsPage(availableReports) {
    return {
        isModalOpen: false, isLoading: false, currentReport: {}, reports: availableReports,
        currentSummary: {},
        // EDITED: Add state to store the detailed report data for printing
        currentData: [],
        reportHtml: `<div class="text-center text-gray-500 py-12"><i class="fas fa-chart-bar text-5xl text-gray-300"></i><p class="mt-4">اختر الفلاتر واضغط "عرض التقرير" لتوليد التقرير.</p></div>`,
        
        openReportModal(report) {
            this.currentReport = report; this.isModalOpen = true;
            this.currentSummary = {};
            this.currentData = []; // EDITED: Clear detailed data
            this.reportHtml = `<div class="text-center text-gray-500 py-12"><i class="fas fa-chart-bar text-5xl text-gray-300"></i><p class="mt-4">اختر الفلاتر واضغط "عرض التقرير" لتوليد التقرير.</p></div>`;
            setTimeout(() => {
                const today = new Date().toISOString().split('T')[0];
                const lastMonth = new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0];
                const form = document.getElementById('reportFilters');
                form.querySelector('input[name="filters[date_to]"]').value = today;
                form.querySelector('input[name="filters[date_from]"]').value = lastMonth;
            }, 100);
        },
        
        generateReport(format) {
            this.isLoading = true;
            if(format === 'view') {
                this.currentSummary = {};
                this.currentData = []; // EDITED: Clear detailed data before fetch
            }
            const formData = new FormData(document.getElementById('reportFilters'));
            formData.append('action', 'generate_report');
            formData.append('report_type', this.currentReport.id);
            formData.append('export_format', format);
            
            if (format === 'csv' || format === 'pdf') {
                const form = document.createElement('form');
                form.method = 'POST'; form.action = '';
                for (let [key, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = key; input.value = value;
                    form.appendChild(input);
                }
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                this.isLoading = false;
                return;
            }

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                if (result.success) { this.displayReportData(result.data, result.summary); } 
                else { this.reportHtml = `<div class="text-center text-red-500 py-12"><i class="fas fa-exclamation-triangle text-5xl"></i><p class="mt-4 font-bold">حدث خطأ:</p><p>${result.error}</p></div>`; }
            }).catch(err => { this.reportHtml = `<div class="text-center text-red-500 py-12"><p>خطأ في الاتصال بالخادم.</p></div>`; })
            .finally(() => { this.isLoading = false; });
        },

        displayReportData(data, summary) {
            this.currentSummary = summary || {};
            this.currentData = data || []; // EDITED: Store detailed data
            let html = '';
            if (summary && Object.keys(summary).length > 0) {
                html += '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">';
                for (const [key, value] of Object.entries(summary)) { html += `<div class="bg-gray-100 p-4 rounded-lg text-center"><p class="text-sm text-gray-600 font-medium">${key}</p><p class="text-xl font-bold text-mai-brown">${value}</p></div>`; }
                html += '</div>';
            }
            if (data && data.length > 0) {
                html += '<div class="overflow-x-auto border rounded-lg"><table class="w-full text-sm">';
                html += '<thead class="bg-gray-50"><tr>';
                for (const key of Object.keys(data[0])) { html += `<th class="p-3 text-right font-bold text-gray-600">${key}</th>`; }
                html += '</tr></thead><tbody>';
                data.forEach(row => {
                    html += '<tr class="border-t hover:bg-gray-50">';
                    for (const value of Object.values(row)) { html += `<td class="p-3 whitespace-nowrap">${value !== null && value !== '' ? value : '-'}</td>`; }
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<div class="text-center text-gray-500 py-12"><i class="fas fa-box-open text-5xl text-gray-300"></i><p class="mt-4">لا توجد بيانات تطابق الفلاتر المحددة.</p></div>';
            }
            this.reportHtml = html;
        },

        // EDITED: This function is now heavily modified to be more flexible
        printSummaryReceipt() {
            if (this.currentReport.id === 'invoices') {
                this.printInvoiceSummary();
            } else if (this.currentReport.id === 'sales_by_item') {
                this.printSalesByItemDetail();
            }
        },
        
        // This function is for the original invoice summary print
        printInvoiceSummary() {
            const form = document.getElementById('reportFilters');
            const dateFrom = form.querySelector('input[name="filters[date_from]"]').value;
            const dateTo = form.querySelector('input[name="filters[date_to]"]').value;
            let summaryHtml = '';
            for (const [key, value] of Object.entries(this.currentSummary)) {
                summaryHtml += `<div class="line"><span>${key}</span><span>${value}</span></div>`;
            }
            const html = this.getReceiptHtml('ملخص تقرير الفواتير', dateFrom, dateTo, '', summaryHtml);
            this._printJob(html);
        },

        // EDITED: This is the NEW function for the detailed item print
        printSalesByItemDetail() {
            const form = document.getElementById('reportFilters');
            const dateFrom = form.querySelector('input[name="filters[date_from]"]').value;
            const dateTo = form.querySelector('input[name="filters[date_to]"]').value;
            
            // 1. Build the detailed items list
            let itemsHtml = '';
            if(this.currentData.length > 0){
                this.currentData.forEach(item => {
                    // Note: We use the translated keys returned by the server
                    const name = item['اسم الصنف'] || 'N/A';
                    const qty = item['الكمية المباعة'] || 0;
                    itemsHtml += `<div class="item-line"><span class="name">${name}</span><span class="qty">${qty}x</span></div>`;
                });
            } else {
                itemsHtml = "<p>لا توجد أصناف مباعة.</p>";
            }

            // 2. Build the summary totals
            let summaryHtml = '';
            for (const [key, value] of Object.entries(this.currentSummary)) {
                summaryHtml += `<div class="line"><span>${key}</span><span>${value}</span></div>`;
            }
            
            // 3. Get the final receipt HTML
            const html = this.getReceiptHtml('ملخص المبيعات حسب الصنف', dateFrom, dateTo, itemsHtml, summaryHtml);
            this._printJob(html);
        },

        // EDITED: Generic receipt HTML builder
        getReceiptHtml(title, dateFrom, dateTo, itemsContent, summaryContent) {
            const receiptStyles = `
                <style>
                    body { font-family: 'Courier New', Courier, monospace; width: 78mm; margin: 0 auto; padding: 10px; color: #000; font-size: 13px; line-height: 1.4; }
                    .receipt { text-align: right; } .header { text-align: center; margin-bottom: 10px; }
                    .header h2 { margin: 0; font-size: 16px; font-weight: bold; }
                    .details { margin-bottom: 8px; font-size: 12px; border-top: 1px dashed #000; padding-top: 8px; }
                    .line, .item-line { display: flex; justify-content: space-between; margin-bottom: 4px; }
                    .line span:first-child, .item-line .name { text-align: right; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
                    .line span:last-child, .item-line .qty { text-align: left; font-weight: bold; flex-shrink: 0; padding-left: 10px; }
                    .separator { border: none; border-top: 1px dashed #000; margin: 8px 0; }
                    .summary-data, .items-list { margin-top: 10px; } 
                    .footer { text-align: center; margin-top: 15px; font-size: 11px; }
                </style>`;
            
            return `
                <html>
                    <head><title>Report Summary</title>${receiptStyles}</head>
                    <body>
                        <div class="receipt">
                            <div class="header"><h2>${title}</h2></div>
                            <div class="details">
                                <div class="line"><span>من تاريخ:</span><span>${dateFrom || 'N/A'}</span></div>
                                <div class="line"><span>إلى تاريخ:</span><span>${dateTo || 'N/A'}</span></div>
                                <div class="line"><span>تاريخ الطباعة:</span><span>${new Date().toLocaleString('ar-SA')}</span></div>
                            </div>
                            ${itemsContent ? `<div class="items-list">${itemsContent}</div>` : ''}
                            ${(itemsContent && summaryContent) ? '<hr class="separator">' : ''}
                            ${summaryContent ? `<div class="summary-data">${summaryContent}</div>` : ''}
                            <div class="footer">. . .</div>
                        </div>
                    </body>
                </html>`;
        },

        async _printJob(htmlContent) {
            const frame = document.getElementById('print-frame'); if (!frame) return;
            frame.contentDocument.open(); frame.contentDocument.write(htmlContent); frame.contentDocument.close();
            await new Promise(resolve => setTimeout(resolve, 300));
            frame.contentWindow.focus(); frame.contentWindow.print();
        }
    }
}
</script>

<?php 
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>