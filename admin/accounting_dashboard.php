<?php
// =================================================================
// MAI SALON - ACCOUNTING HUB (CENTRAL MANAGEMENT PAGE)
// FINAL VERSION WITH INCOME STATEMENT, FILTERS, EXPORTS, AND PARTNERS
// =================================================================

// =================================================================
// DATABASE CHANGE REQUIRED FOR PARTNER BALANCES
// =================================================================
// Please run the following SQL query on your database to add the
// column needed to track partner balances for profit distribution.
//
// ALTER TABLE `partners` ADD `current_balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00;
//
// =================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- SETUP AND SECURITY ---
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'accounting_functions.php';

// Load Composer's autoloader for TCPDF and other libraries
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

// --- PERMISSION CHECK ---
checkAdminAuth();
requirePermission('accounting.dashboard.view'); // Main permission for the page

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin();

// --- FILTER & ACTION SETUP ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$activeTab = $_GET['tab'] ?? 'dashboard';

// =================================================================
// =================== EXPORT ACTION HANDLER =======================
// =================================================================
if ($action === 'export' && isset($_GET['report']) && isset($_GET['format'])) {
    requirePermission('accounting.reports.export'); // Permission to export
    
    $report_type = $_GET['report'];
    $format = $_GET['format'];

    // --- CSV (EXCEL) EXPORT LOGIC ---
    if ($format === 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        $filename = "{$report_type}_report_{$start_date}_to_{$end_date}.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        if ($report_type === 'trial_balance') {
            fputcsv($output, ['كود الحساب', 'اسم الحساب', 'مدين', 'دائن', 'الرصيد', 'نوع الرصيد']);
            $data = getTrialBalanceData($db, $start_date, $end_date);
            foreach ($data as $row) {
                $balance = ($row['normal_balance'] === 'debit') ? $row['total_debit'] - $row['total_credit'] : $row['total_credit'] - $row['total_debit'];
                $balance_type = $balance >= 0 ? ($row['normal_balance'] === 'debit' ? 'مدين' : 'دائن') : ($row['normal_balance'] === 'debit' ? 'دائن (عكسي)' : 'مدين (عكسي)');
                fputcsv($output, [$row['account_code'], $row['account_name_ar'], number_format($row['total_debit'], 2), number_format($row['total_credit'], 2), number_format(abs($balance), 2), $balance_type]);
            }
        } elseif ($report_type === 'income_statement') {
            $data = getIncomeStatementData($db, $start_date, $end_date);
            fputcsv($output, ['البيان', 'المبلغ']);
            fputcsv($output, ['--- الإيرادات ---', '']);
            fputcsv($output, ['إيرادات بطاقة', number_format($data['revenues']['card'], 2)]);
            fputcsv($output, ['إيرادات كاش', number_format($data['revenues']['cash'], 2)]);
            fputcsv($output, ['إيرادات تحويل', number_format($data['revenues']['transfer'], 2)]);
            fputcsv($output, ['اجمالي الإيرادات', number_format($data['revenues']['total'], 2)]);
            fputcsv($output, ['--- المصروفات ---', '']);
            foreach ($data['expenses'] as $expense) {
                fputcsv($output, [$expense['expense_category'], number_format($expense['total_amount'], 2)]);
            }
            fputcsv($output, ['اجمالي المصروفات', number_format($data['total_expenses'], 2)]);
            fputcsv($output, ['--- الملخص ---', '']);
            fputcsv($output, ['صافي الربح / الخسارة', number_format($data['net_profit'], 2)]);
        }
        fclose($output);
        exit;
    }

    // --- PDF EXPORT LOGIC (RE-STYLED) ---
    if ($format === 'pdf' && class_exists('TCPDF')) {
        class ReportPDF extends TCPDF {
            // A shared class for consistent styling
        }

        $pdf = new ReportPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->setRTL(true);
        $pdf->SetCreator('MAI SALON');
        $pdf->SetAuthor('MAI Salon System');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->SetFont('dejavusans', '', 12);
        
        $pdf->AddPage();
        $pdf->SetFillColor(248, 248, 249); // Light page background
        $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');

        $html = '';

        if ($report_type === 'income_statement') {
            $data = getIncomeStatementData($db, $start_date, $end_date);
            $pdf->SetTitle('قائمة الدخل');
            $html = '
            <style>
                body { font-family: "DejaVu Sans", sans-serif; direction: rtl; text-align: right; color: #333; }
                .card { width: 95%; margin: 10mm auto; background: #ffffff; border-radius: 8px; padding: 10mm; border: 1px solid #e9ecef; }
                .title { text-align: center; font-size: 22px; font-weight: bold; color: #212529; margin-bottom: 4px; }
                .title-sub { text-align: center; font-size: 12px; color: #6c757d; margin-bottom: 15px; }
                .section-header { font-size: 16px; font-weight: bold; color: #495057; margin-top: 10px; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 2px solid #dee2e6; }
                .item-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f3f5; }
                .item-label { color: #495057; font-size: 12px; }
                .item-value { font-weight: bold; font-size: 12px; font-family: monospace; }
                .total-row { display: flex; justify-content: space-between; padding-top: 8px; margin-top: 5px; border-top: 1px solid #ced4da; }
                .total-label { font-weight: bold; font-size: 13px; }
                .total-value-revenue { color: #28a745; font-weight: bold; font-size: 13px; font-family: monospace; }
                .total-value-expense { color: #dc3545; font-weight: bold; font-size: 13px; font-family: monospace; }
                .net-profit-section { margin-top: 20px; padding: 12px; border-radius: 6px; background-color: ' . ($data['net_profit'] >= 0 ? '#e6f4ea' : '#fbe9e7') . '; border: 1px solid ' . ($data['net_profit'] >= 0 ? '#b7e4c7' : '#ffcdd2') . '; }
                .net-profit-row { display: flex; justify-content: space-between; align-items: center; }
                .net-profit-label { font-size: 16px; font-weight: bold; color: #212529; }
                .net-profit-value { font-size: 18px; font-weight: bold; font-family: monospace; color: ' . ($data['net_profit'] >= 0 ? '#155724' : '#721c24') . '; }
                .foot-note { margin-top: 15px; font-size: 10px; color: #6c757d; text-align: center; }
            </style>
            <div class="card">
                <div class="title">قائمة الدخل</div>
                <div class="title-sub">عن الفترة من ' . htmlspecialchars($start_date) . ' إلى ' . htmlspecialchars($end_date) . '</div>
                
                <div class="section-header">الإيرادات</div>';
                foreach ($data['revenues'] as $key => $value) {
                    if($key !== 'total') {
                        $label = ['card' => 'إيرادات بطاقة', 'cash' => 'إيرادات كاش', 'transfer' => 'إيرادات تحويل'][$key];
                        $html .= '<div class="item-row"><span class="item-label">' . $label . '</span><span class="item-value">' . number_format($value, 2) . '</span></div>';
                    }
                }
            $html .= '<div class="total-row"><span class="total-label">اجمالي الإيرادات</span><span class="total-value-revenue">' . number_format($data['revenues']['total'], 2) . '</span></div>
                
                <div class="section-header">المصروفات</div>';
                if (empty($data['expenses'])) {
                    $html .= '<div class="item-row"><span class="item-label">لا توجد مصروفات مسجلة</span><span class="item-value">0.00</span></div>';
                } else {
                    foreach ($data['expenses'] as $expense) {
                        $html .= '<div class="item-row"><span class="item-label">' . htmlspecialchars($expense['expense_category']) . '</span><span class="item-value">' . number_format($expense['total_amount'], 2) . '</span></div>';
                    }
                }
            $html .= '<div class="total-row"><span class="total-label">اجمالي المصروفات</span><span class="total-value-expense">' . number_format($data['total_expenses'], 2) . '</span></div>

                <div class="net-profit-section">
                    <div class="net-profit-row">
                        <span class="net-profit-label">صافي الربح / الخسارة</span>
                        <span class="net-profit-value">' . number_format($data['net_profit'], 2) . ' ر.س</span>
                    </div>
                </div>
                <div class="foot-note">Generated BY MAI SALON</div>
            </div>';

        } elseif ($report_type === 'trial_balance') {
            $data = getTrialBalanceData($db, $start_date, $end_date);
            $pdf->SetTitle('ميزان المراجعة');
            $grand_total_debit = 0; $grand_total_credit = 0;
            
            $html = '
            <style>
                body { font-family: "DejaVu Sans", sans-serif; direction: rtl; text-align: right; color: #222; }
                .card { width: 95%; margin: 10mm auto; background: #ffffff; border-radius: 8px; padding: 10mm; border: 1px solid #ebebeb; }
                .title { text-align: center; font-size: 22px; font-weight: bold; color: #212529; margin-bottom: 4px; }
                .title-sub { text-align: center; font-size: 12px; color: #6c757d; margin-bottom: 12px; }
                .report-table { width: 100%; border-collapse: collapse; }
                .report-table th, .report-table td { border: 1px solid #e5e7eb; padding: 9px; font-size: 10.5px; text-align: center; }
                .report-table th { background-color: #f8f9fa; font-weight: bold; color: #495057; }
                .report-table .account-cell { text-align: right; }
                .report-table tfoot td { font-weight: bold; background-color: #f8f9fa; font-size: 12px; }
                .report-table .balance-cell { font-weight: bold; }
                .foot-note { margin-top: 12px; font-size: 10px; color: #6b7280; text-align: center; }
            </style>
            <div class="card">
                <div class="title">ميزان المراجعة</div>
                <div class="title-sub">عن الفترة من ' . htmlspecialchars($start_date) . ' إلى ' . htmlspecialchars($end_date) . '</div>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="account-cell" style="width: 40%;">الحساب</th>
                            <th style="width: 20%;">مدين</th>
                            <th style="width: 20%;">دائن</th>
                            <th style="width: 20%;">الرصيد</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach ($data as $row) {
                        $grand_total_debit += (float)$row['total_debit'];
                        $grand_total_credit += (float)$row['total_credit'];
                        $balance = ($row['normal_balance'] === 'debit') ? $row['total_debit'] - $row['total_credit'] : $row['total_credit'] - $row['total_debit'];
                        $balance_type = $balance >= 0 ? ($row['normal_balance'] === 'debit' ? 'مدين' : 'دائن') : ($row['normal_balance'] === 'debit' ? 'دائن' : 'مدين');

                        $html .= '<tr>
                                    <td class="account-cell">' . htmlspecialchars($row['account_name_ar']) . '<br/><small style="color:#888;">' . htmlspecialchars($row['account_code']) . '</small></td>
                                    <td>' . number_format($row['total_debit'], 2) . '</td>
                                    <td>' . number_format($row['total_credit'], 2) . '</td>
                                    <td class="balance-cell">' . number_format(abs($balance), 2) . '<br/><small style="color:#888;">(' . $balance_type . ')</small></td>
                                  </tr>';
                    }
            $html .= '</tbody>
                    <tfoot>
                        <tr>
                            <td class="account-cell">الإجمالي</td>
                            <td>' . number_format($grand_total_debit, 2) . '</td>
                            <td>' . number_format($grand_total_credit, 2) . '</td>
                            <td>' . (abs($grand_total_debit - $grand_total_credit) < 0.01 ? 'متوازن' : 'غير متوازن') . '</td>
                        </tr>
                    </tfoot>
                </table>
                <div class="foot-note">Generated BY MAI SALON</div>
            </div>';
        }

        if (ob_get_length()) { @ob_end_clean(); }
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output("{$report_type}_report_{$start_date}_to_{$end_date}.pdf", 'D');
        exit;
    }
}


// --- CENTRAL POST ACTION HANDLER ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $redirectTab = $_POST['active_tab'] ?? $activeTab;
        
        if ($action === 'add_account' || $action === 'edit_account') {
            requirePermission('accounting.chart_of_accounts.manage'); // Permission to manage accounts
            if ($action === 'add_account') {
                $stmt = $db->prepare("INSERT INTO accounting_charts_of_accounts (account_code, account_name_ar, account_name_en, account_type_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['account_code'], $_POST['account_name_ar'], $_POST['account_name_en'], $_POST['account_type_id']]);
                $_SESSION['success'] = 'تمت إضافة الحساب بنجاح.';
            } else {
                $stmt = $db->prepare("UPDATE accounting_charts_of_accounts SET account_code = ?, account_name_ar = ?, account_name_en = ?, account_type_id = ? WHERE id = ?");
                $stmt->execute([$_POST['account_code'], $_POST['account_name_ar'], $_POST['account_name_en'], $_POST['account_type_id'], $_POST['account_id']]);
                $_SESSION['success'] = 'تم تحديث الحساب بنجاح.';
            }
            $redirectTab = 'chart_of_accounts';
        }

        if ($action === 'distribute_profit') {
            requirePermission('accounting.profit_distribution.manage');
            $db->beginTransaction();

            $settings = getAccountingSettings($db);
            $retained_earnings_acct_id = $settings['default_retained_earnings_account_id'] ?? null;
            $partners_payable_acct_id = $settings['default_partners_payable_account_id'] ?? null;

            if (!$retained_earnings_acct_id || !$partners_payable_acct_id) {
                throw new Exception('يرجى تحديد حساب الأرباح المحتجزة وحساب جاري الشركاء في الإعدادات أولاً.');
            }

            $distributions = $_POST['distribution'] ?? [];
            $total_distributed = 0;
            foreach ($distributions as $amount) {
                if (!empty($amount) && is_numeric($amount) && $amount > 0) {
                    $total_distributed += (float)$amount;
                }
            }

            if ($total_distributed <= 0) {
                throw new Exception('مبلغ التوزيع يجب أن يكون أكبر من صفر.');
            }

            // Recalculate all-time profit to ensure the data is current before distribution
            $profit_sql = "
                SELECT
                    COALESCE(SUM(CASE WHEN at.name_en = 'Revenue' THEN (CASE WHEN item.type = 'credit' THEN item.amount ELSE -item.amount END) ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN at.name_en = 'Expense' THEN (CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END) ELSE 0 END), 0)
                AS total_net_profit
                FROM accounting_entry_items item
                JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id
                JOIN accounting_account_types at ON coa.account_type_id = at.id
                WHERE at.name_en IN ('Revenue', 'Expense')
            ";
            $available_profit = (float)$db->query($profit_sql)->fetchColumn();

            if ($total_distributed > $available_profit) {
                throw new Exception('لا يمكن توزيع مبلغ أكبر من صافي الربح المتاح. المبلغ المتاح: ' . number_format($available_profit, 2));
            }

            // 1. Create Journal Entry for the distribution
            $entry_date = date('Y-m-d');
            $description = 'تسجيل توزيع أرباح بتاريخ ' . $entry_date;

            $entry_stmt = $db->prepare("INSERT INTO accounting_journal_entries (entry_date, description, created_by_id) VALUES (?, ?, ?)");
            $entry_stmt->execute([$entry_date, $description, $admin['id']]);
            $entry_id = $db->lastInsertId();

            $item_stmt = $db->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)");
            $item_stmt->execute([$entry_id, $retained_earnings_acct_id, 'debit', $total_distributed]); // Debit Retained Earnings
            $item_stmt->execute([$entry_id, $partners_payable_acct_id, 'credit', $total_distributed]); // Credit Partners Payable

            // 2. Update each partner's balance column
            $partner_update_stmt = $db->prepare("UPDATE partners SET current_balance = current_balance + ? WHERE id = ?");
            foreach ($distributions as $partner_id => $amount) {
                 if (!empty($amount) && is_numeric($amount) && $amount > 0) {
                    $partner_update_stmt->execute([(float)$amount, (int)$partner_id]);
                }
            }

            $db->commit();
            $_SESSION['success'] = 'تم تسجيل عملية توزيع الأرباح وتوليد القيد بنجاح.';
            $redirectTab = 'profit_distribution';
        }

        if (isset($_POST['save_settings'])) {
            requirePermission('accounting.settings.manage'); // Permission to manage settings
            $settings_to_save = $_POST['settings'] ?? [];
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO accounting_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
            foreach ($settings_to_save as $key => $value) {
                if (!empty($value)) {
                    $stmt->bindParam(':key', $key); $stmt->bindParam(':value', $value); $stmt->execute();
                }
            }
            $db->commit();
            $_SESSION['success'] = 'تم حفظ إعدادات المحاسبة بنجاح!';
            $redirectTab = 'settings';
        }
        
        header('Location: accounting_dashboard.php?tab=' . $redirectTab . '&start_date=' . $start_date . '&end_date=' . $end_date);
        exit;
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error'] = 'فشل العملية: ' . $e->getMessage();
    header('Location: accounting_dashboard.php?tab=' . $activeTab . '&start_date=' . $start_date . '&end_date=' . $end_date);
    exit;
}

// --- DATA FETCHING FOR ALL TABS ---
$page_title = 'المركز المحاسبي';

$income_statement_data = getIncomeStatementData($db, $start_date, $end_date);
$trial_balance_data = getTrialBalanceData($db, $start_date, $end_date);
$partners = $db->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// *** REVISED: Calculate All-Time Net Profit for distribution with a more accurate query ***
$all_time_profit_sql = "
    SELECT
        -- Calculate net of all revenue accounts (credits are positive, debits are negative)
        COALESCE(SUM(CASE WHEN at.name_en = 'Revenue' THEN (CASE WHEN item.type = 'credit' THEN item.amount ELSE -item.amount END) ELSE 0 END), 0) -
        -- Calculate net of all expense accounts (debits are positive, credits are negative)
        COALESCE(SUM(CASE WHEN at.name_en = 'Expense' THEN (CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END) ELSE 0 END), 0)
    AS total_net_profit
    FROM accounting_entry_items item
    JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id
    JOIN accounting_account_types at ON coa.account_type_id = at.id
    WHERE at.name_en IN ('Revenue', 'Expense')
";
$total_net_profit_for_distribution = (float)$db->query($all_time_profit_sql)->fetchColumn();


// --- Summary Calculation for Dashboard ---
$summary = ['assets' => 0, 'liabilities' => 0, 'equity' => 0, 'revenue' => 0, 'expenses' => 0, 'net_profit' => 0, 'vat' => 0];

// Get default account IDs from settings
$current_settings = getAccountingSettings($db);
$vat_account_id = $current_settings['default_vat_liability_account_id'] ?? 0;

// Summary for the period (Income Statement items)
$is_sql = "
    SELECT 
        at.name_en AS account_type, 
        coa.id as account_id,
        COALESCE(SUM(CASE WHEN item.type = 'credit' THEN item.amount WHEN item.type = 'debit' THEN -item.amount ELSE 0 END), 0) as balance
    FROM accounting_charts_of_accounts coa 
    JOIN accounting_account_types at ON coa.account_type_id = at.id 
    JOIN accounting_entry_items item ON coa.id = item.account_id 
    JOIN accounting_journal_entries entry ON item.entry_id = entry.id 
    WHERE entry.entry_date BETWEEN ? AND ? AND at.name_en IN ('Revenue', 'Expense')
    GROUP BY coa.id, at.name_en";
$is_stmt = $db->prepare($is_sql);
$is_stmt->execute([$start_date, $end_date]);
while ($row = $is_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['account_type'] === 'Revenue') {
        $summary['revenue'] += $row['balance'];
    }
    if ($row['account_type'] === 'Expense') {
        // Expenses are debits, so we subtract the negative balance to make it a positive expense number
        $summary['expenses'] -= $row['balance'];
    }
}

// *** NEW: Calculate VAT separately for the period ***
if ($vat_account_id) {
    $vat_sql = "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as vat_collected FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id WHERE item.account_id = ? AND entry.entry_date BETWEEN ? AND ?";
    $vat_stmt = $db->prepare($vat_sql);
    $vat_stmt->execute([$vat_account_id, $start_date, $end_date]);
    $summary['vat'] = (float)$vat_stmt->fetchColumn();
}
// Adjust revenue to be pre-tax
// $summary['revenue'] = $summary['revenue'] - $summary['vat']; // Commented out to show gross revenue
$summary['net_profit'] = $summary['revenue'] - $summary['expenses'];


// Balance Sheet Summary (up to end date)
$bs_sql = "SELECT at.name_en AS account_type, at.normal_balance, COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END), 0) as net_activity FROM accounting_charts_of_accounts coa JOIN accounting_account_types at ON coa.account_type_id = at.id LEFT JOIN accounting_entry_items item ON coa.id = item.account_id LEFT JOIN accounting_journal_entries entry ON item.entry_id = entry.id AND entry.entry_date <= ? WHERE at.name_en IN ('Asset', 'Liability', 'Equity') GROUP BY coa.id, at.name_en, at.normal_balance";
$bs_stmt = $db->prepare($bs_sql);
$bs_stmt->execute([$end_date]);
while ($row = $bs_stmt->fetch(PDO::FETCH_ASSOC)) {
    $balance = ($row['normal_balance'] === 'debit') ? (float)$row['net_activity'] : -(float)$row['net_activity'];
    $key = strtolower($row['account_type']) . 's';
    if (isset($summary[$key])) $summary[$key] += $balance;
}

// =================================================================
// =================== NEW: DATA FOR LINE CHART ====================
// =================================================================
$monthly_data_sql = "
    SELECT
        DATE_FORMAT(entry.entry_date, '%Y-%m') AS month,
        SUM(CASE WHEN at.name_en = 'Revenue' THEN (CASE WHEN item.type = 'credit' THEN item.amount ELSE -item.amount END) ELSE 0 END) AS monthly_revenue,
        SUM(CASE WHEN at.name_en = 'Expense' THEN (CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END) ELSE 0 END) AS monthly_expense
    FROM accounting_journal_entries entry
    JOIN accounting_entry_items item ON entry.id = item.entry_id
    JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id
    JOIN accounting_account_types at ON coa.account_type_id = at.id
    WHERE entry.entry_date BETWEEN ? AND ? AND at.name_en IN ('Revenue', 'Expense')
    GROUP BY month
    ORDER BY month ASC
";
$monthly_stmt = $db->prepare($monthly_data_sql);
$monthly_stmt->execute([$start_date, $end_date]);
$monthly_chart_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_revenues = [];
$chart_expenses = [];
$chart_profits = [];

// Helper array for Arabic month names
$arabic_months = ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"];

foreach ($monthly_chart_data as $row) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['month']);
    $month_index = (int)$dateObj->format('n') - 1;
    $chart_labels[] = $arabic_months[$month_index];
    
    $revenue = (float)$row['monthly_revenue'];
    $expense = (float)$row['monthly_expense'];
    
    $chart_revenues[] = $revenue;
    $chart_expenses[] = $expense;
    $chart_profits[] = $revenue - $expense;
}
// =================== END OF NEW DATA FETCHING ====================


// Other data fetching
$opening_entry_exists = $db->query("SELECT id FROM accounting_journal_entries WHERE description = 'قيد افتتاحي للنظام'")->fetch();
$accounts_list = $db->query("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE is_active = 1 ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_accounts_for_coa_tab = $db->query("SELECT coa.*, at.name_ar AS type_name_ar FROM accounting_charts_of_accounts coa JOIN accounting_account_types at ON coa.account_type_id = at.id ORDER BY coa.account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$account_types = $db->query("SELECT * FROM accounting_account_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$settings_fields = [
    'default_cash_account_id' => ['label' => 'الحساب الافتراضي للنقدية (الصندوق)', 'icon' => 'fa-cash-register'],
    'default_card_account_id' => ['label' => 'الحساب الافتراضي للبطاقة (البنك)', 'icon' => 'fa-credit-card'],
    'default_transfer_account_id' => ['label' => 'الحساب الافتراضي للتحويل البنكي', 'icon' => 'fa-money-bill-transfer'],
    'default_sales_revenue_account_id' => ['label' => 'حساب إيرادات مبيعات المنتجات', 'icon' => 'fa-box-open'],
    'default_service_revenue_account_id' => ['label' => 'حساب إيرادات الخدمات', 'icon' => 'fa-concierge-bell'],
    'default_vat_liability_account_id' => ['label' => 'حساب ضريبة القيمة المضافة', 'icon' => 'fa-file-invoice-dollar'],
    'default_sales_discount_account_id' => ['label' => 'حساب الخصم المسموح به', 'icon' => 'fa-percent'],
    'default_inventory_account_id' => ['label' => 'حساب المخزون', 'icon' => 'fa-boxes-stacked'],
    'default_cogs_account_id' => ['label' => 'حساب تكلفة البضاعة المباعة', 'icon' => 'fa-receipt'],
    // *** NEW SETTINGS FOR PROFIT DISTRIBUTION ***
    'default_retained_earnings_account_id' => ['label' => 'حساب الأرباح المحتجزة (للتوزيع)', 'icon' => 'fa-piggy-bank'],
    'default_partners_payable_account_id' => ['label' => 'حساب جاري الشركاء (دائن)', 'icon' => 'fa-hand-holding-dollar']
];

ob_start();
?>
<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Main container with Alpine.js state -->
<div x-data="pageController()">
    
    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
         <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-800 self-center sm:self-auto"><?= $page_title ?></h1>
            <form @submit.prevent="applyFilter()" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 text-sm w-full sm:w-auto" x-show="['dashboard', 'trial_balance', 'income_statement'].includes(activeTab)">
                <div class="flex items-center gap-2">
                    <label class="font-semibold shrink-0">من:</label>
                    <input type="date" x-model="startDate" @change="applyFilter()" class="p-2 border rounded-lg w-full">
                </div>
                <div class="flex items-center gap-2">
                    <label class="font-semibold shrink-0">إلى:</label>
                    <input type="date" x-model="endDate" @change="applyFilter()" class="p-2 border rounded-lg w-full">
                </div>
                <button type="submit" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown w-full sm:w-auto shrink-0"><i class="fas fa-filter"></i> تطبيق</button>
            </form>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="mb-6 overflow-x-auto">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-4 sm:space-x-6" aria-label="Tabs">
                <?php if (hasPermission('accounting.dashboard.view')): ?>
                <a :href="getTabUrl('dashboard')" @click.prevent="activeTab = 'dashboard'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'dashboard' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-chart-pie mr-2"></i> المركز المالي </a>
                <?php endif; ?>
                
                <?php if (hasPermission('accounting.income_statement.view')): ?>
                <a :href="getTabUrl('income_statement')" @click.prevent="activeTab = 'income_statement'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'income_statement' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-file-invoice-dollar mr-2"></i> قائمة الدخل </a>
                <?php endif; ?>

                <?php if (hasPermission('accounting.profit_distribution.view')): ?>
                <a :href="getTabUrl('profit_distribution')" @click.prevent="activeTab = 'profit_distribution'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'profit_distribution' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-hands-holding-dollar mr-2"></i> توزيع الأرباح </a>
                <?php endif; ?>

                <?php if (hasPermission('accounting.trial_balance.view')): ?>
                <a :href="getTabUrl('trial_balance')" @click.prevent="activeTab = 'trial_balance'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'trial_balance' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-scale-balanced mr-2"></i> ميزان المراجعة </a>
                <?php endif; ?>
                
                <?php if (hasPermission('accounting.chart_of_accounts.view')): ?>
                <a :href="getTabUrl('chart_of_accounts')" @click.prevent="activeTab = 'chart_of_accounts'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'chart_of_accounts' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-sitemap mr-2"></i> شجرة الحسابات </a>
                <?php endif; ?>
                
                <a href="partners_management.php" class="group inline-flex items-center py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-users mr-2"></i> الشركاء </a>
                
                <?php if (hasPermission('accounting.settings.view')): ?>
                <a :href="getTabUrl('settings')" @click.prevent="activeTab = 'settings'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'settings' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-cogs mr-2"></i> الإعدادات </a>
                <?php endif; ?>

                <?php if (hasPermission('accounting.opening_entry.view')): ?>
                <a :href="getTabUrl('opening_entry')" @click.prevent="activeTab = 'opening_entry'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'opening_entry' }" class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"> <i class="fa-solid fa-book-open mr-2"></i> القيد الافتتاحي </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- TAB: Dashboard -->
        <?php if (hasPermission('accounting.dashboard.view')): ?>
        <div x-show="activeTab === 'dashboard'" x-transition class="space-y-6">
            <!-- REVISED: Dashboard cards to match image -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                 <?php 
                    $dashboardCards = [
                        ['label' => 'إجمالي الإيرادات (للفترة)', 'value' => $summary['revenue'], 'icon' => 'fa-arrow-up', 'color' => 'green'],
                        ['label' => 'إجمالي المصروفات (للفترة)', 'value' => $summary['expenses'], 'icon' => 'fa-arrow-down', 'color' => 'red'],
                        ['label' => 'صافي الربح (للفترة)', 'value' => $summary['net_profit'], 'icon' => 'fa-wallet', 'color' => $summary['net_profit'] >= 0 ? 'blue' : 'red'],
                        ['label' => 'إجمالي الأصول (حتى تاريخه)', 'value' => $summary['assets'], 'icon' => 'fa-building-columns', 'color' => 'indigo'],
                        ['label' => 'إجمالي الالتزامات (حتى تاريخه)', 'value' => $summary['liabilities'], 'icon' => 'fa-file-invoice', 'color' => 'yellow']
                    ];
                 ?>
                 <?php foreach($dashboardCards as $card): ?>
                 <div class="bg-white rounded-xl shadow p-4 flex items-center gap-4">
                     <div class="bg-<?= $card['color'] ?>-100 text-<?= $card['color'] ?>-600 rounded-full w-12 h-12 flex-shrink-0 flex items-center justify-center text-xl">
                         <i class="fas <?= $card['icon'] ?>"></i>
                     </div>
                     <div>
                         <p class="text-sm text-gray-500 font-medium"><?= $card['label'] ?></p>
                         <h2 class="text-xl sm:text-2xl font-bold text-gray-800">
                             <?= number_format($card['value'], 2) ?> 
                             <span class="text-sm font-semibold">ر.س</span>
                         </h2>
                     </div>
                 </div>
                <?php endforeach; ?>
            </div>
            
            <!-- REVISED: Chart area with Chart.js -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <!-- Left Chart: Monthly Trend Line Chart -->
                <div class="bg-white rounded-xl shadow p-4 sm:p-6">
                    <canvas id="financeLineChart" height="200"></canvas>
                </div>

                <!-- Right Chart: Period Summary Bar Chart -->
                <div class="bg-white rounded-xl shadow p-4 sm:p-6">
                    <h3 class="text-lg font-bold mb-4 text-center text-gray-700">ملخص الإيرادات والمصروفات</h3>
                    <canvas id="financeBarChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB: Income Statement -->
        <?php if (hasPermission('accounting.income_statement.view')): ?>
        <div x-show="activeTab === 'income_statement'" x-cloak x-transition>
            <?php if (hasPermission('accounting.reports.export')): ?>
            <div class="flex justify-end gap-2 mb-4">
                <a :href="getExportUrl('income_statement', 'excel')" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700"><i class="fas fa-file-excel mr-2"></i>تصدير إكسل</a>
                <a :href="getExportUrl('income_statement', 'pdf')" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700"><i class="fas fa-file-pdf mr-2"></i>تصدير PDF</a>
            </div>
            <?php endif; ?>
            <div class="bg-white rounded-xl shadow-md max-w-4xl mx-auto">
                <div class="p-4 sm:p-6 border-b">
                    <h2 class="text-2xl font-bold text-center text-gray-800">قائمة الدخل</h2>
                    <p class="text-center text-sm text-gray-500">
                        عن الفترة من <?= htmlspecialchars($start_date) ?> إلى <?= htmlspecialchars($end_date) ?>
                    </p>
                </div>
                <div class="p-4 sm:p-6">
                    <!-- Revenues Section -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold text-mai-brown border-b-2 border-mai-brown pb-2 mb-3">الإيرادات</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center"><span class="text-gray-600">إيرادات نقاط بيع (بطاقة)</span> <span class="font-mono font-semibold"><?= number_format($income_statement_data['revenues']['card'], 2) ?></span></div>
                            <div class="flex justify-between items-center"><span class="text-gray-600">إيرادات كاش</span> <span class="font-mono font-semibold"><?= number_format($income_statement_data['revenues']['cash'], 2) ?></span></div>
                            <div class="flex justify-between items-center"><span class="text-gray-600">إيرادات تحويل</span> <span class="font-mono font-semibold"><?= number_format($income_statement_data['revenues']['transfer'], 2) ?></span></div>
                            <div class="flex justify-between items-center border-t pt-2 mt-2"><span class="font-bold">اجمالي الإيرادات</span> <span class="font-mono font-bold text-green-600"><?= number_format($income_statement_data['revenues']['total'], 2) ?></span></div>
                        </div>
                    </div>
                    <!-- Expenses Section -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold text-mai-brown border-b-2 border-mai-brown pb-2 mb-3">المصروفات</h3>
                        <div class="space-y-2 text-sm">
                             <?php if(empty($income_statement_data['expenses'])): ?>
                                <p class="text-gray-500">لا توجد مصروفات مسجلة لهذه الفترة.</p>
                            <?php else: ?>
                                <?php foreach($income_statement_data['expenses'] as $expense): ?>
                                    <div class="flex justify-between items-center"><span class="text-gray-600"><?= htmlspecialchars($expense['expense_category']) ?></span> <span class="font-mono font-semibold"><?= number_format($expense['total_amount'], 2) ?></span></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="flex justify-between items-center border-t pt-2 mt-2"><span class="font-bold">اجمالي المصروفات</span> <span class="font-mono font-bold text-red-600"><?= number_format($income_statement_data['total_expenses'], 2) ?></span></div>
                        </div>
                    </div>
                    
                    <!-- Net Profit Section (Distribution part removed) -->
                    <div class="border-t-2 py-4 space-y-3">
                        <div class="flex justify-between items-center text-lg">
                            <span class="font-bold">صافي ربح / خسارة الفترة</span> 
                            <span class="font-mono font-bold text-xl <?= $income_statement_data['net_profit'] >= 0 ? 'text-blue-600' : 'text-red-600' ?>">
                                <?= number_format($income_statement_data['net_profit'], 2) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Retained Earnings Section -->
                    <div class="bg-gray-50 rounded-lg p-4 mt-4 space-y-2">
                        <div class="flex justify-between items-c
                        
                        
                        
                        enter text-sm"><span class="text-gray-600">رصيد الأرباح أول الفترة</span> <span class="font-mono font-semibold"><?= number_format($income_statement_data['retained_earnings_start'], 2) ?></span></div>
                        <div class="flex justify-between items-center text-sm font-bold"><span class="">رصيد الأرباح آخر الفترة</span> <span class="font-mono"><?= number_format($income_statement_data['retained_earnings_end'], 2) ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB: PROFIT DISTRIBUTION -->
        <?php if (hasPermission('accounting.profit_distribution.view')): ?>
        <div x-show="activeTab === 'profit_distribution'" x-cloak x-transition>
            <div class="bg-white rounded-xl shadow-md max-w-4xl mx-auto" x-data="profitDistributionManager(<?= $total_net_profit_for_distribution ?>)">
                <div class="p-4 sm:p-6 border-b">
                    <h2 class="text-2xl font-bold text-center text-gray-800">توزيع الأرباح</h2>
                    <p class="text-center text-sm text-gray-500">
                        توزيع يدوي للأرباح المتراكمة
                    </p>
                </div>
                <form method="POST" action="accounting_dashboard.php?tab=profit_distribution&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                    <input type="hidden" name="action" value="distribute_profit">
                    <input type="hidden" name="active_tab" value="profit_distribution">
                    <div class="p-4 sm:p-6">
                        <!-- Total Profit Display -->
                        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <h3 class="text-md font-semibold text-gray-600">إجمالي صافي الربح المتراكم (القابل للتوزيع)</h3>
                            <p class="text-3xl font-bold font-mono my-2"
                               :class="totalProfit >= 0 ? 'text-blue-700' : 'text-red-600'" 
                               x-text="formatCurrency(totalProfit)"></p>
                        </div>

                        <!-- Partners Distribution Inputs -->
                        <div class="space-y-4 mb-6">
                            <h4 class="text-lg font-bold text-mai-brown border-b-2 border-mai-brown pb-2 mb-3">توزيع الشركاء</h4>
                            <?php if(empty($partners)): ?>
                                <p class="text-center text-gray-500">لا يوجد شركاء معرفين. <a href="partners_management.php" class="text-mai-brown underline">إضافة شريك</a></p>
                            <?php else: ?>
                                <?php foreach($partners as $index => $partner): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 items-center gap-4">
                                    <label for="partner_<?= $partner['id'] ?>" class="font-semibold text-gray-700"><?= htmlspecialchars($partner['name']) ?></label>
                                    <input type="number" step="0.01" min="0" 
                                           id="partner_<?= $partner['id'] ?>" 
                                           name="distribution[<?= $partner['id'] ?>]"
                                           x-model.number="distributions[<?= $index ?>].amount"
                                           class="p-2 border rounded-lg w-full text-left font-mono" 
                                           placeholder="0.00">
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Summary -->
                        <div class="border-t-2 py-4 mt-4 space-y-3">
                             <div class="flex justify-between items-center text-md">
                                <span class="font-semibold text-gray-600">المبلغ الموزع</span> 
                                <span class="font-mono font-bold text-green-600" x-text="formatCurrency(totalDistributed)"></span>
                            </div>
                            <div class="flex justify-between items-center text-lg">
                                <span class="font-bold">الربح المتبقي بعد التوزيع</span> 
                                <span class="font-mono font-bold" 
                                      :class="remainingProfit >= 0 ? 'text-blue-600' : 'text-red-600'" 
                                      x-text="formatCurrency(remainingProfit)">
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php if (hasPermission('accounting.profit_distribution.manage')): ?>
                    <div class="bg-gray-50 px-6 py-4 flex justify-end">
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-bold disabled:bg-gray-400 disabled:cursor-not-allowed" :disabled="totalDistributed <= 0 || remainingProfit < 0">
                            <i class="fas fa-save mr-2"></i> حفظ وتوليد القيد المحاسبي
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB: Trial Balance -->
        <?php if (hasPermission('accounting.trial_balance.view')): ?>
        <div x-show="activeTab === 'trial_balance'" x-cloak x-transition>
            <?php if (hasPermission('accounting.reports.export')): ?>
            <div class="flex justify-end gap-2 mb-4">
                <a :href="getExportUrl('trial_balance', 'excel')" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700"><i class="fas fa-file-excel mr-2"></i>تصدير إكسل</a>
                <a :href="getExportUrl('trial_balance', 'pdf')" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700"><i class="fas fa-file-pdf mr-2"></i>تصدير PDF</a>
            </div>
            <?php endif; ?>
            <div class="bg-white rounded-xl shadow-md">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-right text-sm font-medium">كود/اسم الحساب</th><th class="px-4 py-3 text-center text-sm font-medium">مدين</th><th class="px-4 py-3 text-center text-sm font-medium">دائن</th><th class="px-4 py-3 text-center text-sm font-medium">الرصيد النهائي</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if(empty($trial_balance_data)): ?>
                                <tr><td colspan="4" class="text-center py-10 text-gray-500">لا توجد حركات للفترة المحددة.</td></tr>
                            <?php endif; $grand_total_debit = 0; $grand_total_credit = 0; foreach ($trial_balance_data as $row): $total_debit = (float)$row['total_debit']; $total_credit = (float)$row['total_credit']; $grand_total_debit += $total_debit; $grand_total_credit += $total_credit; $balance = ($row['normal_balance'] === 'debit') ? $total_debit - $total_credit : $total_credit - $total_debit; ?>
                            <tr>
                                <td class="px-4 py-4"><div class="font-medium text-gray-800"><?= htmlspecialchars($row['account_name_ar']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($row['account_code']) ?></div></td>
                                <td class="px-4 py-4 text-center text-gray-700"><?= number_format($total_debit, 2) ?></td>
                                <td class="px-4 py-4 text-center text-gray-700"><?= number_format($total_credit, 2) ?></td>
                                <td class="px-4 py-4 text-center font-bold <?= $balance >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= number_format(abs($balance), 2) ?> <span class="text-xs font-normal text-gray-500"><?= $balance >= 0 ? ($row['normal_balance'] === 'debit' ? 'مدين' : 'دائن') : ($row['normal_balance'] === 'debit' ? '(دائن)' : '(مدين)') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td class="px-4 py-4 text-left font-bold text-lg">الإجمالي</td>
                                <td class="px-4 py-4 text-center font-bold text-lg"><?= number_format($grand_total_debit, 2) ?> ر.س</td>
                                <td class="px-4 py-4 text-center font-bold text-lg"><?= number_format($grand_total_credit, 2) ?> ر.س</td>
                                <td class="px-4 py-4 text-center"><?php if (abs($grand_total_debit - $grand_total_credit) < 0.01): ?><span class="px-3 py-1 text-sm rounded-full font-semibold bg-green-100 text-green-800">متوازن</span><?php else: ?><span class="px-3 py-1 text-sm rounded-full font-semibold bg-red-100 text-red-800">غير متوازن</span><?php endif; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB: Chart of Accounts -->
        <?php if (hasPermission('accounting.chart_of_accounts.view')): ?>
        <div x-show="activeTab === 'chart_of_accounts'" x-cloak x-transition>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">شجرة الحسابات</h1>
                        <p class="text-sm text-gray-500">إدارة جميع الحسابات المالية.</p>
                    </div>
                    <?php if (hasPermission('accounting.chart_of_accounts.manage')): ?>
                    <div><button @click="$dispatch('open-modal', { id: 'account-modal' })" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 font-bold w-full sm:w-auto"><i class="fas fa-plus"></i> إضافة حساب جديد</button></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-right text-xs font-medium">كود/اسم الحساب</th><th class="px-6 py-3 text-right text-xs font-medium">النوع</th><th class="px-6 py-3 text-center text-xs font-medium">الإجراءات</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><?php foreach ($all_accounts_for_coa_tab as $account): ?><tr><td class="px-6 py-4"><div class="font-medium text-gray-800"><?= htmlspecialchars($account['account_name_ar']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($account['account_code']) ?></div></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($account['type_name_ar']) ?></td><td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium"><?php if (hasPermission('accounting.chart_of_accounts.manage')): ?><button @click="$dispatch('open-modal', { id: 'account-modal', isEdit: true, account: <?= htmlspecialchars(json_encode($account)) ?> })" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i> تعديل</button><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        </div>
        <?php endif; ?>

        <!-- TAB: Settings -->
        <?php if (hasPermission('accounting.settings.view')): ?>
        <div x-show="activeTab === 'settings'" x-cloak x-transition>
             <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6"><div class="flex items-center gap-4"><div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white text-xl"><i class="fas fa-cogs"></i></div><div><h1 class="text-2xl font-bold text-gray-800">إعدادات المحاسبة</h1><p class="text-sm text-gray-500">ربط العمليات التلقائية بالحسابات الصحيحة.</p></div></div></div>
            <form method="POST" action="accounting_dashboard.php"><input type="hidden" name="active_tab" value="settings"><input type="hidden" name="save_settings" value="1"><div class="bg-white rounded-xl shadow-md p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6"><?php foreach ($settings_fields as $key => $field): ?>
                <div class="flex items-start gap-3"><i class="fas <?= $field['icon'] ?> text-mai-brown text-xl mt-1"></i><div class="w-full"><label for="<?= $key ?>" class="block text-sm font-medium text-gray-800 mb-1"><?= $field['label'] ?></label><select id="<?= $key ?>" name="settings[<?= $key ?>]" class="w-full p-2 border border-gray-300 rounded-lg bg-gray-50 focus:ring-mai-brown focus:border-mai-brown" required><option value="">-- اختر حساب --</option><?php foreach ($accounts_list as $account): ?><option value="<?= $account['id'] ?>" <?php if (isset($current_settings[$key]) && $current_settings[$key] == $account['id']) echo ' selected'; ?>>[<?= htmlspecialchars($account['account_code']) ?>] <?= htmlspecialchars($account['account_name_ar']) ?></option><?php endforeach; ?></select></div></div>
            <?php endforeach; ?></div>
            <?php if (hasPermission('accounting.settings.manage')): ?>
            <div class="mt-8 border-t pt-6 flex justify-end"><button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-bold text-lg transition-colors"><i class="fas fa-save mr-2"></i>حفظ الإعدادات</button></div>
            <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- TAB: Opening Entry -->
        <?php if (hasPermission('accounting.opening_entry.view')): ?>
        <div x-show="activeTab === 'opening_entry'" x-cloak x-transition>
            <div class="bg-white rounded-xl shadow-md p-4 sm:p-6">
                <?php if ($opening_entry_exists): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p class="font-bold">مكتمل</p><p>تم تسجيل القيد الافتتاحي للنظام مسبقاً.</p></div>
                <?php else: ?>
                <form method="POST" action="process_opening_entry.php" x-data="openingEntryManager()">
                    <div class="mb-4 max-w-sm"><label class="block text-sm font-medium">تاريخ القيد</label><input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required class="mt-1 w-full p-2 border rounded-lg"></div>
                    <div class="overflow-x-auto border rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-right text-sm">الحساب</th><th class="px-4 py-3 text-right text-sm">مدين</th><th class="px-4 py-3 text-right text-sm">دائن</th><th></th></tr></thead>
                            <tbody><template x-for="(row, index) in rows" :key="index"><tr class="bg-white"><td class="p-2 w-full sm:w-1/2"><select :name="`account_id[${index}]`" class="w-full p-2 border rounded-lg bg-white" required><option value="">-- اختر حساب --</option><?php foreach ($accounts_list as $account): ?><option value="<?= $account['id'] ?>">[<?= $account['account_code'] ?>] <?= htmlspecialchars($account['account_name_ar']) ?></option><?php endforeach; ?></select></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`debit[${index}]`" x-model.number="row.debit" @input="validateRow(index, 'debit')" class="w-full p-2 border rounded-lg" placeholder="0.00"></td><td class="p-2"><input type="number" step="0.01" min="0" :name="`credit[${index}]`" x-model.number="row.credit" @input="validateRow(index, 'credit')" class="w-full p-2 border rounded-lg" placeholder="0.00"></td><td class="p-2 text-center"><button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="text-red-500 hover:text-red-700 text-2xl">&times;</button></td></tr></template></tbody>
                            <tfoot class="bg-gray-100"><tr><td class="p-3 font-bold text-left">الإجمالي</td><td class="p-3"><span class="font-bold text-lg" x-text="totals.debit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></td><td class="p-3"><span class="font-bold text-lg" x-text="totals.credit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></td><td></td></tr></tfoot>
                        </table>
                    </div>
                    <?php if (hasPermission('accounting.opening_entry.manage')): ?>
                    <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <button type="button" @click="addRow()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 w-full sm:w-auto"><i class="fas fa-plus mr-1"></i> إضافة سطر</button>
                        <div class="flex items-center gap-4"><div x-show="!isBalanced()" class="text-red-600 font-semibold">القيد غير متوازن!</div><button type="submit" :disabled="!isBalanced() || totals.debit === 0" class="bg-green-600 text-white px-8 py-2 rounded-lg hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed"><i class="fas fa-save mr-1"></i> حفظ القيد الافتتاحي</button></div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<?php if (hasPermission('accounting.chart_of_accounts.manage')): ?>
<div x-data="accountModal()" @open-modal.window="openModal($event.detail)" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4">
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" @click="show = false"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form method="POST" action="accounting_dashboard.php"><input type="hidden" name="active_tab" value="chart_of_accounts"><input type="hidden" name="action" :value="isEdit ? 'edit_account' : 'add_account'"><input type="hidden" name="account_id" :value="account.id"><div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4"><h3 class="text-lg leading-6 font-medium text-gray-900" x-text="isEdit ? 'تعديل حساب' : 'إضافة حساب جديد'"></h3><div class="mt-4 space-y-4"><div><label class="block text-sm font-medium text-gray-700">كود الحساب</label><input type="text" name="account_code" x-model="account.account_code" required class="mt-1 w-full p-2 border rounded-lg"></div><div><label class="block text-sm font-medium text-gray-700">اسم الحساب (عربي)</label><input type="text" name="account_name_ar" x-model="account.account_name_ar" required class="mt-1 w-full p-2 border rounded-lg"></div><div><label class="block text-sm font-medium text-gray-700">اسم الحساب (إنجليزي)</label><input type="text" name="account_name_en" x-model="account.account_name_en" class="mt-1 w-full p-2 border rounded-lg"></div><div><label class="block text-sm font-medium text-gray-700">نوع الحساب</label><select name="account_type_id" x-model="account.account_type_id" required class="mt-1 w-full p-2 border rounded-lg bg-white"><option value="">-- اختر النوع --</option><?php foreach ($account_types as $type): ?><option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name_ar']) ?></option><?php endforeach; ?></select></div></div></div><div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"><button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-mai-brown text-base font-medium text-white hover:bg-mai-dark-brown sm:ml-3 sm:w-auto sm:text-sm" x-text="isEdit ? 'حفظ التعديلات' : 'إنشاء الحساب'"></button><button type="button" @click="show = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">إلغاء</button></div></form>
        </div>
    </div>
</div>
<?php endif; ?>


<script>
// Pass PHP data to JavaScript
const monthlyLabels = <?= json_encode($chart_labels) ?>;
const monthlyRevenues = <?= json_encode($chart_revenues) ?>;
const monthlyExpenses = <?= json_encode($chart_expenses) ?>;
const monthlyProfits = <?= json_encode($chart_profits) ?>;
const summaryRevenue = <?= $summary['revenue'] ?>;
const summaryExpenses = <?= $summary['expenses'] ?>;

document.addEventListener('alpine:init', () => {
    Alpine.data('pageController', () => ({
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'dashboard',
        startDate: '<?php echo htmlspecialchars($start_date); ?>',
        endDate: '<?php echo htmlspecialchars($end_date); ?>',
        
        applyFilter() {
            const url = this.getTabUrl(this.activeTab);
            window.location.href = url;
        },
        
        getTabUrl(tabName) {
            let url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            url.searchParams.set('start_date', this.startDate);
            url.searchParams.set('end_date', this.endDate);
            url.searchParams.delete('action');
            url.searchParams.delete('report');
            url.searchParams.delete('format');
            return url.toString();
        },

        getExportUrl(reportName, format) {
            let url = new URL(window.location.href);
            url.searchParams.set('action', 'export');
            url.searchParams.set('report', reportName);
            url.searchParams.set('format', format);
            url.searchParams.set('start_date', this.startDate);
            url.searchParams.set('end_date', this.endDate);
            return url.toString();
        }
    }));

    Alpine.data('openingEntryManager', () => ({
        rows: [{ debit: '', credit: '' }],
        addRow() { this.rows.push({ debit: '', credit: '' }); },
        removeRow(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
        validateRow(index, field) { if (field === 'debit' && this.rows[index].debit > 0) this.rows[index].credit = ''; else if (field === 'credit' && this.rows[index].credit > 0) this.rows[index].debit = ''; },
        get totals() { return this.rows.reduce((acc, row) => { acc.debit += parseFloat(row.debit || 0); acc.credit += parseFloat(row.credit || 0); return acc; }, { debit: 0, credit: 0 }); },
        isBalanced() { const diff = Math.abs(this.totals.debit - this.totals.credit); return diff < 0.01; }
    }));

    Alpine.data('accountModal', () => ({
        show: false, isEdit: false, account: {},
        openModal(detail) {
            this.isEdit = detail.isEdit || false;
            this.account = this.isEdit ? { ...detail.account } : { id: '', account_code: '', account_name_ar: '', account_name_en: '', account_type_id: '' };
            this.show = true;
        }
    }));

    Alpine.data('profitDistributionManager', (totalProfit) => ({
        totalProfit: totalProfit,
        distributions: <?= json_encode(array_map(function($p) { return ['id' => $p['id'], 'amount' => '']; }, $partners)) ?>,
        
        get totalDistributed() {
            return this.distributions.reduce((sum, dist) => sum + (parseFloat(dist.amount) || 0), 0);
        },
        
        get remainingProfit() {
            return this.totalProfit - this.totalDistributed;
        },

        formatCurrency(value) {
            if (isNaN(value)) {
                return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(0);
            }
            return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(value);
        }
    }));
});

// *** NEW CHART.JS RENDERING LOGIC ***
document.addEventListener('DOMContentLoaded', function () {
    // 1) Line Chart Code
    const lineCtx = document.getElementById('financeLineChart');
    if (lineCtx) {
        new Chart(lineCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: "المصروفات",
                        data: monthlyExpenses,
                        borderColor: "#e74c3c",
                        tension: 0.4,
                        borderWidth: 2,
                        fill: false
                    },
                    {
                        label: "الايرادات",
                        data: monthlyRevenues,
                        borderColor: "#3498db",
                        tension: 0.4,
                        borderWidth: 2,
                        fill: false
                    },
                    {
                        label: "الربح",
                        data: monthlyProfits,
                        borderColor: "#7f8c8d",
                        tension: 0.4,
                        borderWidth: 2,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "top",
                        labels: { font: { size: 14 } }
                    }
                },
                scales: {
                    x: { 
                        title: { display: true, text: "الأشهر" },
                        grid: { display: false }
                    },
                    y: { 
                        title: { display: true, text: "المبلغ" },
                        grid: { color: "rgba(0,0,0,0.05)" }
                    }
                }
            }
        });
    }

    // 2) Bar Chart Code
    const barCtx = document.getElementById('financeBarChart');
    if(barCtx) {
        new Chart(barCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ["ملخص الفترة"],
                datasets: [
                    {
                        label: "الإيرادات",
                        data: [summaryRevenue],
                        backgroundColor: "#2ecc71"
                    },
                    {
                        label: "المصروفات",
                        data: [summaryExpenses],
                        backgroundColor: "#e74c3c"
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: "top" }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,0,0,0.05)" }
                    }
                }
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';

// Helper function needed for data fetching
function getTrialBalanceData($db, $start_date, $end_date) {
    $sql = "SELECT 
                coa.account_code, 
                coa.account_name_ar, 
                at.normal_balance, 
                COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE 0 END), 0) AS total_debit, 
                COALESCE(SUM(CASE WHEN item.type = 'credit' THEN item.amount ELSE 0 END), 0) AS total_credit 
            FROM 
                accounting_charts_of_accounts coa
            JOIN 
                accounting_account_types at ON coa.account_type_id = at.id 
            LEFT JOIN 
                accounting_entry_items item ON coa.id = item.account_id 
            LEFT JOIN 
                accounting_journal_entries entry ON item.entry_id = entry.id AND entry.entry_date BETWEEN ? AND ? 
            GROUP BY 
                coa.id, coa.account_code, coa.account_name_ar, at.normal_balance 
            HAVING 
                total_debit > 0 OR total_credit > 0 
            ORDER BY 
                coa.account_code ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>