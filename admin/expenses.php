<?php
// --- FILE INCLUDES ---
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';
require_once 'accounting_functions.php';
// IMPORTANT: Include the file containing your permission functions.
require_once 'users_functions.php';

// If you installed TCPDF via Composer, this line is essential.
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

checkAdminAuth();
// =================================================================
// =================== PERMISSION CHECK: PAGE ACCESS ===============
// =================================================================
// A user must have 'expenses.view' to access any part of this page.
requirePermission('expenses.view');

$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// =================================================================
// =================== FILTERING LOGIC SETUP =======================
// =================================================================
$filter_year = !empty($_GET['year']) ? (int)$_GET['year'] : null;
$filter_month = !empty($_GET['month']) ? (int)$_GET['month'] : null;
$filter_day = !empty($_GET['day']) ? (int)$_GET['day'] : null;

$where_clauses = [];
$params = [];

if ($filter_year) {
    $where_clauses[] = "YEAR(e.expense_date) = ?";
    $params[] = $filter_year;
}
if ($filter_month) {
    $where_clauses[] = "MONTH(e.expense_date) = ?";
    $params[] = $filter_month;
}
if ($filter_day) {
    $where_clauses[] = "DAY(e.expense_date) = ?";
    $params[] = $filter_day;
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : '';

// =================================================================
// ==================== PDF EXPORT LOGIC (RE-STYLED) ===============
// =================================================================

/**
 * Custom PDF class for consistent styling
 */
class StyledPDF extends TCPDF {
    // You can add common header/footer logic here if needed in the future.
}

if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // PERMISSION CHECK: User must have view permission to export a report.
    requirePermission('expenses.view');
    
    if (!class_exists('TCPDF')) { die("Error: TCPDF library not found."); }

    $sql_pdf = "SELECT e.*, u.name as user_name, coa.account_name_ar as paid_from_account_name FROM expenses e LEFT JOIN users u ON e.user_id = u.id LEFT JOIN accounting_charts_of_accounts coa ON e.paid_from_account_id = coa.id" . $where_sql . " ORDER BY e.expense_date DESC, e.id DESC";
    $expenses_stmt = $db->prepare($sql_pdf);
    $expenses_stmt->execute($params);
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new StyledPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setRTL(true);
    $pdf->SetCreator('MAI SALON');
    $pdf->SetAuthor('MAI Salon System');
    $pdf->SetTitle('تقرير المصروفات');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('dejavusans', '', 12);

    $pdf->AddPage();
    $pdf->SetFillColor(248, 248, 249);
    $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');

    // Build filter description
    $filter_desc = 'كامل المدة';
    if ($filter_year && $filter_month && $filter_day) {
        $filter_desc = "تاريخ: " . $filter_year . '-' . $filter_month . '-' . $filter_day;
    } elseif ($filter_year && $filter_month) {
        $filter_desc = "شهر: " . date('F', mktime(0, 0, 0, $filter_month, 1)) . ', ' . $filter_year;
    } elseif ($filter_year) {
        $filter_desc = "سنة: " . $filter_year;
    }

    $html = '
    <style>
        body { font-family: "DejaVu Sans", sans-serif; direction: rtl; text-align: right; color: #222; }
        .card { width: 95%; margin: 10mm auto; background: #ffffff; border-radius: 8px; padding: 10mm; border: 1px solid #ebebeb; box-sizing: border-box; }
        .title { text-align: center; font-size: 22px; font-weight: 700; color: #222222; margin-bottom: 4px; }
        .title-sub { width: 50%; margin: 0 auto 10px auto; height: 2px; background: #e9eef6; }
        .info { background: #fbfbfc; border: 1px solid #f1f1f2; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; font-size: 12.5px; text-align: center; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { border: 1px solid #e5e7eb; padding: 9px; font-size: 11px; }
        .report-table th { background-color: #f8f9fa; font-weight: bold; color: #495057; }
        .report-table tfoot td { font-weight: bold; background-color: #f8f9fa; font-size: 12px; }
        .total-amount { color: #d9534f; }
        .foot-note { margin-top: 12px; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
    <div class="card">
        <div class="title">تقرير المصروفات</div>
        <div class="title-sub"></div>
        <div class="info">
            <strong>فلترة حسب:</strong> ' . $filter_desc . '
        </div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>التاريخ</th><th>الفئة</th><th>الوصف</th><th>المبلغ (ر.س)</th><th>مدفوع من</th><th>المستخدم</th>
                </tr>
            </thead>
            <tbody>';
    $totalAmount = 0;
    foreach ($expenses as $expense) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($expense['expense_date']) . '</td>
                    <td>' . htmlspecialchars($expense['expense_category']) . '</td>
                    <td>' . htmlspecialchars($expense['description'] ?: '-') . '</td>
                    <td style="font-weight: bold; color: #c0392b;">' . number_format($expense['amount'], 2) . '</td>
                    <td>' . htmlspecialchars($expense['paid_from_account_name'] ?: 'غير محدد') . '</td>
                    <td>' . htmlspecialchars($expense['user_name'] ?: 'غير معروف') . '</td>
                  </tr>';
        $totalAmount += $expense['amount'];
    }
    if (empty($expenses)) {
        $html .= '<tr><td colspan="6" style="text-align:center; padding: 20px;">لا توجد بيانات لعرضها.</td></tr>';
    }
    $html .= '</tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:left;">الإجمالي</td>
                    <td colspan="3" class="total-amount">' . number_format($totalAmount, 2) . ' ر.س</td>
                </tr>
            </tfoot>
        </table>
        <div class="foot-note">Generated BY MAI SALON</div>
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    if (ob_get_length()) { @ob_end_clean(); }
    $pdf->Output('expenses_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_single_pdf' && !empty($_GET['id'])) {
    // PERMISSION CHECK: User must have view permission to print a single voucher.
    requirePermission('expenses.view');
    
    if (!class_exists('TCPDF')) { die("Error: TCPDF library not found."); }
    $expenseId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT e.*, u.name as user_name, coa.account_name_ar as paid_from_account_name FROM expenses e LEFT JOIN users u ON e.user_id = u.id LEFT JOIN accounting_charts_of_accounts coa ON e.paid_from_account_id = coa.id WHERE e.id = ?");
    $stmt->execute([$expenseId]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$expense) { die('Expense not found.'); }

    $pdf = new StyledPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setRTL(true);
    $pdf->SetCreator('MAI SALON');
    $pdf->SetAuthor(htmlspecialchars($expense['user_name'] ?: 'System'));
    $pdf->SetTitle('سند صرف - ' . $expenseId);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->SetFont('dejavusans', '', 12);
    
    $pdf->AddPage();
    $pdf->SetFillColor(248, 248, 249);
    $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');

    $html = '
    <style>
        body { font-family: "DejaVu Sans", sans-serif; direction: rtl; text-align: right; color: #222; }
        .card { width: 90%; margin: 12mm auto; background: #ffffff; border-radius: 6px; padding: 12mm; border: 1px solid #ebebeb; }
        .title { text-align: center; font-size: 24px; font-weight: 700; color: #c0392b; margin-bottom: 6px; }
        .title-sub { width: 40%; margin: 0 auto 12px auto; height: 2px; background: #e9eef6; }
        .info-grid { border: 1px solid #f1f1f2; border-radius: 6px; overflow: hidden; }
        .info-row { display: flex; border-bottom: 1px solid #f1f1f2; }
        .info-row:last-child { border-bottom: none; }
        .info-label { background: #fbfbfc; color: #6b7280; width: 28%; text-align: right; font-weight: 600; padding: 9px; border-left: 1px solid #f1f1f2; }
        .info-value { color: #111827; width: 70%; text-align: right; padding: 9px; }
        .info-value.amount { font-size: 16px; font-weight: bold; color: #c0392b; }
        .signatures { margin-top: 25mm; width: 100%; font-size: 12px; }
        .signatures-table { width: 100%; border: none; }
        .signatures-table td { text-align: center; color: #333; }
        .foot-note { margin-top: 12px; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
    <div class="card">
        <div class="title">سند صرف</div>
        <div class="title-sub"></div>
        <div class="info-grid">
            <div class="info-row"><div class="info-label">رقم السند:</div><div class="info-value">EXP-' . $expense['id'] . '</div></div>
            <div class="info-row"><div class="info-label">تاريخ الصرف:</div><div class="info-value">' . htmlspecialchars($expense['expense_date']) . '</div></div>
            <div class="info-row"><div class="info-label">المبلغ:</div><div class="info-value amount">' . number_format($expense['amount'], 2) . ' ريال سعودي</div></div>
            <div class="info-row"><div class="info-label">فئة المصروف:</div><div class="info-value">' . htmlspecialchars($expense['expense_category']) . '</div></div>
            <div class="info-row"><div class="info-label">البيان:</div><div class="info-value">' . nl2br(htmlspecialchars($expense['description'] ?: '-')) . '</div></div>
            <div class="info-row"><div class="info-label">تم الصرف من حساب:</div><div class="info-value">' . htmlspecialchars($expense['paid_from_account_name'] ?: 'غير محدد') . '</div></div>
            <div class="info-row"><div class="info-label">تم الصرف بواسطة:</div><div class="info-value">' . htmlspecialchars($expense['user_name'] ?: 'غير معروف') . '</div></div>
        </div>
        <div class="signatures">
            <table class="signatures-table">
                <tr>
                    <td>توقيع المحاسب <br/><br/> .............................</td>
                    <td>توقيع المستلم <br/><br/> .............................</td>
                </tr>
            </table>
        </div>
        <div class="foot-note">Generated BY MAI SALON</div>
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    if (ob_get_length()) { @ob_end_clean(); }
    $pdf->Output('expense_voucher_' . $expenseId . '.pdf', 'I'); // 'I' for inline display
    exit;
}

// --- START: REFACTORED & IMPROVED CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (empty($action)) {
            throw new Exception("No action specified.");
        }

        $db->beginTransaction();
        $current_admin_id = $admin['id'];
        
        if ($action === 'add_expense' || $action === 'edit_expense') {
            // --- Centralized Validation for Add/Edit ---
            $expense_account_id = filter_input(INPUT_POST, 'expense_account_id', FILTER_VALIDATE_INT);
            $paid_from_account_id = filter_input(INPUT_POST, 'paid_from_account_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $expense_date = $_POST['expense_date'] ?? null;
            $description = trim($_POST['description'] ?? '');

            if (empty($expense_account_id) || empty($paid_from_account_id)) {
                throw new Exception("الرجاء تحديد فئة المصروف والحساب الذي تم الدفع منه.");
            }
            if ($amount === false || $amount <= 0) {
                throw new Exception("الرجاء إدخال مبلغ صحيح وأكبر من صفر.");
            }
            if (empty($expense_date)) {
                 throw new Exception("الرجاء تحديد تاريخ المصروف.");
            }
            if (!function_exists('createJournalEntry')) {
                error_log("FATAL ERROR: createJournalEntry function is not defined in accounting_functions.php");
                throw new Exception("خطأ فادح في النظام. الرجاء مراجعة مدير النظام.");
            }

            // Fetch the category name from its account ID
            $cat_stmt = $db->prepare("SELECT account_name_ar FROM accounting_charts_of_accounts WHERE id = ? AND account_type_id = 5");
            $cat_stmt->execute([$expense_account_id]);
            $expense_category_name = $cat_stmt->fetchColumn();
            if (!$expense_category_name) {
                throw new Exception("فئة المصروف المحددة غير صالحة.");
            }
        }

        switch ($action) {
            case 'add_expense':
                // PERMISSION CHECK:
                requirePermission('expenses.create');
                
                $stmt = $db->prepare( "INSERT INTO expenses (expense_category, description, amount, expense_date, user_id, paid_from_account_id, expense_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)" );
                $stmt->execute([ $expense_category_name, $description, $amount, $expense_date, $current_admin_id, $paid_from_account_id, $expense_account_id ]);
                $expense_id = $db->lastInsertId();
                if(!$expense_id) { throw new Exception("فشل في إنشاء سجل المصروف."); }

                $journal_desc = "مصروف: " . (!empty($description) ? $description : $expense_category_name);
                $journal_items = [['account_id' => $expense_account_id, 'type' => 'debit', 'amount' => $amount], ['account_id' => $paid_from_account_id, 'type' => 'credit', 'amount' => $amount]];
                $entry_id = createJournalEntry($db, $expense_date, $journal_desc, $journal_items, $expense_id, 'expense', $current_admin_id);
                
                $db->prepare("UPDATE expenses SET journal_entry_id = ? WHERE id = ?")->execute([$entry_id, $expense_id]);
                $_SESSION['message'] = 'تم إضافة المصروف والقيد المحاسبي بنجاح.';
                break;

            case 'edit_expense':
                // PERMISSION CHECK:
                requirePermission('expenses.edit');
                $expense_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (empty($expense_id)) { throw new Exception("لم يتم تحديد معرّف المصروف للتعديل."); }

                // Delete old journal entry
                $stmt_old = $db->prepare("SELECT journal_entry_id FROM expenses WHERE id = ?"); $stmt_old->execute([$expense_id]);
                if ($old_journal_id = $stmt_old->fetchColumn()) {
                    $db->prepare("DELETE FROM accounting_entry_items WHERE entry_id = ?")->execute([$old_journal_id]);
                    $db->prepare("DELETE FROM accounting_journal_entries WHERE id = ?")->execute([$old_journal_id]);
                }

                // Create new journal entry
                $journal_desc_edit = "تحديث مصروف: " . (!empty($description) ? $description : $expense_category_name);
                $journal_items_edit = [['account_id' => $expense_account_id, 'type' => 'debit', 'amount' => $amount], ['account_id' => $paid_from_account_id, 'type' => 'credit', 'amount' => $amount]];
                $new_entry_id = createJournalEntry($db, $expense_date, $journal_desc_edit, $journal_items_edit, $expense_id, 'expense', $current_admin_id);
                
                // Update the expense record
                $stmt_update = $db->prepare( "UPDATE expenses SET expense_category = ?, description = ?, amount = ?, expense_date = ?, journal_entry_id = ?, paid_from_account_id = ?, expense_account_id = ? WHERE id = ?" );
                $stmt_update->execute([ $expense_category_name, $description, $amount, $expense_date, $new_entry_id, $paid_from_account_id, $expense_account_id, $expense_id ]);
                $_SESSION['message'] = 'تم تحديث المصروف والقيد المحاسبي بنجاح.';
                break;

            case 'delete_expense':
                // PERMISSION CHECK:
                requirePermission('expenses.delete');
                $expense_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (empty($expense_id)) { throw new Exception("لم يتم تحديد معرّف المصروف للحذف."); }

                $stmt_old_del = $db->prepare("SELECT journal_entry_id FROM expenses WHERE id = ?"); $stmt_old_del->execute([$expense_id]);
                if ($old_journal_id_del = $stmt_old_del->fetchColumn()) {
                    $db->prepare("DELETE FROM accounting_entry_items WHERE entry_id = ?")->execute([$old_journal_id_del]);
                    $db->prepare("DELETE FROM accounting_journal_entries WHERE id = ?")->execute([$old_journal_id_del]);
                }
                $stmt_del = $db->prepare("DELETE FROM expenses WHERE id = ?"); $stmt_del->execute([$expense_id]);
                $_SESSION['message'] = 'تم حذف المصروف والقيد المحاسبي بنجاح.';
                break;
            
            default:
                 throw new Exception("Action not recognized.");
        }
        
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // This is the critical part: ensure you have a mechanism to display this error.
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    // This redirect happens for both success and failure, ensuring the page reloads.
    header('Location: expenses.php'); 
    exit;
}
// --- END: REFACTORED CRUD ACTIONS ---


// --- DATA FETCHING (MODIFIED) ---
try {
    $sql = "SELECT e.*, u.name as user_name, coa.account_name_ar as paid_from_account_name FROM expenses e LEFT JOIN users u ON e.user_id = u.id LEFT JOIN accounting_charts_of_accounts coa ON e.paid_from_account_id = coa.id" . $where_sql . " ORDER BY e.expense_date DESC, e.id DESC";
    $expenses_stmt = $db->prepare($sql);
    $expenses_stmt->execute($params);
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    $years_stmt = $db->query("SELECT DISTINCT YEAR(expense_date) as year FROM expenses ORDER BY year DESC");
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch expense categories from Chart of Accounts (Account Type ID 5 is for Expenses)
    $categories_stmt = $db->query("SELECT id, account_name_ar FROM accounting_charts_of_accounts WHERE account_type_id = 5 AND is_active = 1 ORDER BY account_name_ar ASC");
    $expense_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    $acc_settings = getAccountingSettings($db);
    $payment_accounts = []; $payment_account_ids = [];
    if (!empty($acc_settings['default_cash_account_id'])) $payment_account_ids[] = $acc_settings['default_cash_account_id'];
    if (!empty($acc_settings['default_card_account_id'])) $payment_account_ids[] = $acc_settings['default_card_account_id'];
    if(!empty($payment_account_ids)) {
        $placeholders = implode(',', array_fill(0, count($payment_account_ids), '?'));
        $stmt = $db->prepare("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE id IN ($placeholders)");
        $stmt->execute($payment_account_ids);
        $payment_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $expenses = []; $payment_accounts = []; $available_years = []; $expense_categories = [];
    $_SESSION['error'] = "خطأ في جلب البيانات: " . $e->getMessage();
}
// --- END: DATA FETCHING ---

$page_title = 'إدارة المصروفات';
ob_start();
?>
<div x-data="expenseManagement(<?php echo htmlspecialchars(json_encode($expenses)); ?>)">
    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center text-white"><i class="fas fa-wallet text-xl"></i></div>
                <div>
                    <h1 class="text-2xl font-bold text-mai-dark-brown">إدارة المصروفات</h1>
                    <p class="text-sm text-gray-500">تسجيل وتتبع جميع مصروفات الصالون.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?php
                $export_params = ['action' => 'export_pdf'];
                if ($filter_year) $export_params['year'] = $filter_year;
                if ($filter_month) $export_params['month'] = $filter_month;
                if ($filter_day) $export_params['day'] = $filter_day;
                ?>
                <a href="expenses.php?<?php echo http_build_query($export_params); ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm flex items-center gap-2"><i class="fas fa-file-pdf"></i><span>تصدير PDF</span></a>
                <?php // PERMISSION CHECK: Show "Add" button only if user has create permission ?>
                <?php if (hasPermission('expenses.create')): ?>
                <button @click="openAddModal()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown text-sm flex items-center gap-2"><i class="fas fa-plus"></i><span>إضافة مصروف</span></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'partials/messages.php'; ?>

    <!-- Filter Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <form action="expenses.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label for="year" class="block text-sm font-medium text-gray-700">السنة</label>
                <select name="year" id="year" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">الكل</option>
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="month" class="block text-sm font-medium text-gray-700">الشهر</label>
                <select name="month" id="month" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">الكل</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($filter_month == $m) ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="day" class="block text-sm font-medium text-gray-700">اليوم</label>
                <input type="number" name="day" id="day" value="<?php echo htmlspecialchars((string)$filter_day); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="e.g., 15">
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2"><i class="fas fa-filter"></i><span>تصفية</span></button>
                <a href="expenses.php" class="w-full bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-center">إلغاء</a>
            </div>
        </form>
    </div>


    <!-- Expenses Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التاريخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الفئة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوصف</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبلغ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مدفوع من</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">المستخدم</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="expense in expenses" :key="expense.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap" x-text="expense.expense_date"></td>
                            <td class="px-6 py-4 whitespace-nowrap" x-text="expense.expense_category"></td>
                            <td class="px-6 py-4" x-text="expense.description || '-'"></td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-red-600" x-text="`${parseFloat(expense.amount).toFixed(2)} ر.س`"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="expense.paid_from_account_name || 'غير محدد'"></td>
                            <td class="px-6 py-4 whitespace-nowrap" x-text="expense.user_name || 'غير معروف'"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a :href="'expenses.php?action=export_single_pdf&id=' + expense.id" target="_blank" class="text-green-600 hover:text-green-900 ml-4" title="طباعة سند الصرف"><i class="fas fa-print"></i></a>
                                <?php // PERMISSION CHECK: Show "Edit" button only if user has edit permission ?>
                                <?php if (hasPermission('expenses.edit')): ?>
                                <a href="#" @click.prevent="openEditModal(expense)" class="text-blue-600 hover:text-blue-900 ml-4"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php // PERMISSION CHECK: Show "Delete" button only if user has delete permission ?>
                                <?php if (hasPermission('expenses.delete')): ?>
                                <form method="POST" action="expenses.php" class="inline-block" @submit.prevent="confirmDelete($event.target)">
                                    <input type="hidden" name="action" value="delete_expense">
                                    <input type="hidden" name="id" :value="expense.id">
                                    <button type="submit" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </template>
                     <tr x-show="expenses.length === 0"><td colspan="7" class="px-6 py-4 text-center text-gray-500">لا توجد مصروفات لعرضها تطابق معايير التصفية.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal (MODIFIED) -->
    <div x-show="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg" @click.outside="isModalOpen = false">
            <form method="POST" action="expenses.php">
                <div class="p-5 border-b flex justify-between items-center"><h3 class="text-xl font-bold text-mai-dark-brown" x-text="modalTitle"></h3><button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-red-600 text-3xl">&times;</button></div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" :value="formMethod">
                    <input type="hidden" name="id" x-model="formData.id">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">التاريخ*</label><input type="date" name="expense_date" x-model="formData.expense_date" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div>
                    
                    <!-- NEW: Expense Category Dropdown -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">فئة المصروف*</label>
                        <select name="expense_account_id" x-model="formData.expense_account_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-white">
                            <option value="">-- اختر فئة المصروف --</option>
                            <?php foreach ($expense_categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['account_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div><label class="block text-sm font-medium text-gray-700 mb-1">المبلغ*</label><input type="number" step="0.01" name="amount" x-model="formData.amount" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="0.00"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">تم الدفع من حساب*</label><select name="paid_from_account_id" x-model="formData.paid_from_account_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-white"><option value="">-- اختر حساب الدفع --</option><?php foreach ($payment_accounts as $p_account): ?><option value="<?= $p_account['id'] ?>"><?= htmlspecialchars($p_account['account_name_ar']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">الوصف (اختياري)</label><textarea name="description" x-model="formData.description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea></div>
                </div>
                <div class="p-4 bg-gray-50 flex justify-end gap-3 border-t"><button type="button" @click="isModalOpen = false" class="px-4 py-2 border rounded-lg text-gray-700 bg-white hover:bg-gray-100">إلغاء</button><button type="submit" class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">حفظ</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    function expenseManagement(expensesData) {
        return {
            expenses: expensesData, isModalOpen: false, modalTitle: '', formMethod: 'add_expense', formData: {},
            init() { this.resetForm(); },
            openAddModal() { this.resetForm(); this.modalTitle = 'إضافة مصروف جديد'; this.formMethod = 'add_expense'; this.isModalOpen = true; },
            openEditModal(expense) {
                this.modalTitle = 'تعديل المصروف';
                this.formMethod = 'edit_expense';
                this.formData = { 
                    id: expense.id, 
                    expense_account_id: expense.expense_account_id, 
                    description: expense.description, 
                    amount: expense.amount, 
                    expense_date: expense.expense_date, 
                    paid_from_account_id: expense.paid_from_account_id 
                }; 
                this.isModalOpen = true; 
            },
            resetForm() { 
                this.formData = { 
                    id: null, 
                    expense_account_id: '', 
                    description: '', 
                    amount: '', 
                    expense_date: new Date().toISOString().slice(0, 10), 
                    paid_from_account_id: '<?php echo $acc_settings['default_cash_account_id'] ?? '' ?>' 
                }; 
            },
            confirmDelete(formElement) { if (confirm('هل أنت متأكد من رغبتك في حذف هذا المصروف؟ سيتم حذف القيد المحاسبي المرتبط به أيضاً.')) { formElement.submit(); } }
        }
    }
</script>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>