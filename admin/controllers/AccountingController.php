<?php
// /controllers/AccountingController.php

// =================================================================
// MAI SALON - ACCOUNTING HUB (MVC CONTROLLER) - REVISED & CORRECTED
// =================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- SETUP AND SECURITY ---
session_start();
require_once(__DIR__ . '/../auth.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../users_functions.php');
require_once(__DIR__ . '/../models/AccountingModel.php');

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// --- PERMISSION CHECK & INITIALIZATION ---
checkAdminAuth();
requirePermission('accounting.dashboard.view');

$database = new Database();
$db = $database->getConnection();
$accountingModel = new AccountingModel($db);
$admin = getCurrentAdmin();

// --- FILTER & ACTION SETUP ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$activeTab = $_GET['tab'] ?? 'dashboard';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// =================================================================
// =================== ACTION HANDLER (POST) =======================
// =================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectTab = $_POST['active_tab'] ?? $activeTab;
    try {
        switch ($action) {
            case 'add_account':
            case 'edit_account':
                requirePermission('accounting.chart_of_accounts.manage');
                $accountingModel->manageAccount($_POST);
                $_SESSION['success'] = 'تم حفظ تفاصيل الحساب بنجاح.';
                $redirectTab = 'chart_of_accounts';
                break;

            case 'delete_account':
                requirePermission('accounting.chart_of_accounts.manage');
                $account_id = $_POST['account_id'] ?? 0;
                $accountingModel->deleteAccount($account_id);
                $_SESSION['success'] = 'تم حذف الحساب بنجاح.';
                $redirectTab = 'chart_of_accounts';
                break;
            
            case 'add_manual_entry':
                // requirePermission('accounting.journal.manage');
                $accountingModel->createManualJournalEntry($_POST, $admin['id']);
                $_SESSION['success'] = 'تم إنشاء القيد اليدوي بنجاح!';
                $redirectTab = 'journal_entries';
                break;
            
            // NEW: Handle Editing a Journal Entry
            case 'edit_journal_entry':
                // requirePermission('accounting.journal.manage');
                $entry_id = $_POST['entry_id'] ?? 0;
                $accountingModel->updateManualJournalEntry($entry_id, $_POST, $admin['id']);
                $_SESSION['success'] = 'تم تحديث القيد بنجاح!';
                $redirectTab = 'journal_entries';
                break;

            // NEW: Handle Deleting a Journal Entry
            case 'delete_journal_entry':
                // requirePermission('accounting.journal.manage');
                $entry_id = $_POST['entry_id'] ?? 0;
                $accountingModel->deleteJournalEntry($entry_id);
                $_SESSION['success'] = 'تم حذف القيد بنجاح.';
                $redirectTab = 'journal_entries';
                break;

            case 'save_settings':
                requirePermission('accounting.settings.manage');
                $settings_to_save = $_POST['settings'] ?? [];
                $accountingModel->saveSettings($settings_to_save);
                $_SESSION['success'] = 'تم حفظ الإعدادات بنجاح!';
                $redirectTab = 'settings';
                break;
        }
        
        header('Location: AccountingController.php?tab=' . $redirectTab . '&start_date=' . $start_date . '&end_date=' . $end_date);
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = 'فشلت العملية: ' . $e->getMessage();
        header('Location: AccountingController.php?tab=' . $redirectTab . '&start_date=' . $start_date . '&end_date=' . $end_date);
        exit;
    }
}

// =================================================================
// =================== ACTION HANDLER (GET/AJAX) ===================
// =================================================================
if ($action === 'get_entry_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        $entryDetails = $accountingModel->getJournalEntryDetails((int)$_GET['id']);
        if (!$entryDetails) {
            // This is what likely causes the error message if the ID is not found for any reason
            throw new Exception('Entry not found in database.');
        }
        echo json_encode(['success' => true, 'data' => $entryDetails]);
    } catch (Exception $e) {
        // Send a proper error response
        http_response_code(404); // Not Found or 500 for general error
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    // CRITICAL: Stop the script from executing further and sending HTML
    exit;
}



// =================================================================
// =========== DATA FETCHING FOR VIEW ==============================
// =================================================================
$page_title = 'لوحة التحكم المالي';

// --- Dashboard & Charts Data ---
$chart_end_date = date('Y-m-t');
$chart_start_date = date('Y-m-01', strtotime('-3 months'));

$pie_month = $_GET['pie_month'] ?? 'all';
if ($pie_month === 'all') {
    $pie_start_date = date('Y-01-01');
    $pie_end_date = date('Y-12-31');
} else {
    $pie_start_date = date('Y-m-01', strtotime($pie_month));
    $pie_end_date = date('Y-m-t', strtotime($pie_month));
}

$monthly_chart_data = $accountingModel->getChartData($chart_start_date, $chart_end_date);
$expense_pie_chart_data = $accountingModel->getExpenseBreakdownForPieChart($pie_start_date, $pie_end_date);

// Process Line Chart data
$chart_labels = []; $chart_revenues = []; $chart_expenses = []; $chart_profits = [];
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

// Process Pie Chart data
$pie_chart_labels = [];
$pie_chart_data = [];
foreach($expense_pie_chart_data as $row) {
    $pie_chart_labels[] = $row['category'];
    $pie_chart_data[] = (float)$row['total_amount'];
}

// --- Data for Specific Tabs ---
$profit_and_loss_data = $accountingModel->getProfitAndLossData($start_date, $end_date);
$cash_flow_data = $accountingModel->getCashFlowData($start_date, $end_date);
$trial_balance_data = $accountingModel->getTrialBalanceData($start_date, $end_date);

// Journal Entries Tab Data with Pagination
$items_per_page = 15;
$total_journal_entries = $accountingModel->countJournalEntries($start_date, $end_date);
$total_pages = ceil($total_journal_entries / $items_per_page);
$journal_entries = $accountingModel->getJournalEntries($start_date, $end_date, $page, $items_per_page);

// Data for settings and forms
$accounts_list = $accountingModel->getAccountsList();
$all_accounts_for_coa_tab = $accountingModel->getAllAccountsForCOATab();
$account_types = $accountingModel->getAccountTypes();
$current_settings = $accountingModel->getAccountingSettings();
$settings_fields = [
    'default_cash_account_id' => ['label' => 'الحساب الافتراضي للنقدية (الصندوق)', 'icon' => 'fa-cash-register'],
    'default_card_account_id' => ['label' => 'الحساب الافتراضي للبطاقة (البنك)', 'icon' => 'fa-credit-card'],
    'default_transfer_account_id' => ['label' => 'الحساب الافتراضي للتحويل البنكي', 'icon' => 'fa-money-bill-transfer'],
    'default_sales_revenue_account_id' => ['label' => 'حساب إيرادات مبيعات المنتجات', 'icon' => 'fa-box-open'],
    'default_service_revenue_account_id' => ['label' => 'حساب إيرادات الخدمات', 'icon' => 'fa-concierge-bell'],
    'default_sales_return_account_id' => ['label' => 'حساب مرتجع المبيعات', 'icon' => 'fa-undo'], // NEW SETTING
    'default_vat_liability_account_id' => ['label' => 'حساب ضريبة القيمة المضافة', 'icon' => 'fa-file-invoice-dollar'],
    'default_sales_discount_account_id' => ['label' => 'حساب الخصم المسموح به', 'icon' => 'fa-percent'],
    'default_inventory_account_id' => ['label' => 'حساب المخزون', 'icon' => 'fa-boxes-stacked'],
    'default_cogs_account_id' => ['label' => 'حساب تكلفة البضاعة المباعة', 'icon' => 'fa-receipt']
];

// =================================================================
// =================== LOAD THE VIEW ===============================
// =================================================================

$page_content_path = '../views/accounting/dashboard_view.php';
require_once '../views/layouts/responsive_base.php';
?>