<?php
// =================================================================
// MAI SALON - PROCESS PROFIT DISTRIBUTION
// =================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once '../config/database.php';
require_once 'accounting_functions.php'; // We need getAccountingSettings()

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: accounting_dashboard.php');
    exit;
}

$distributions = $_POST['distribution'] ?? [];
$entry_date = date('Y-m-d');

// --- VALIDATION ---
if (empty($distributions)) {
    $_SESSION['error'] = 'لم يتم إرسال بيانات للتوزيع.';
    header('Location: accounting_dashboard.php?tab=profit_distribution');
    exit;
}

// Calculate total distribution from submitted data
$total_distribution_amount = 0;
foreach ($distributions as $partner_id => $amount) {
    if (!is_numeric($amount) || (float)$amount < 0) {
        $_SESSION['error'] = 'المبالغ المدخلة غير صالحة.';
        header('Location: accounting_dashboard.php?tab=profit_distribution');
        exit;
    }
    if ((float)$amount > 0) {
        $total_distribution_amount += (float)$amount;
    }
}

if ($total_distribution_amount <= 0) {
    $_SESSION['error'] = 'يجب أن يكون المبلغ الإجمالي الموزع أكبر من صفر.';
    header('Location: accounting_dashboard.php?tab=profit_distribution');
    exit;
}


// --- ACCOUNTING LOGIC ---
try {
    $db->beginTransaction();

    // 1. Server-side check of available profit (NEVER trust client-side data for this)
    $all_time_profit_sql = "
        SELECT 
            (SELECT COALESCE(SUM(item.amount), 0) FROM accounting_entry_items item JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id JOIN accounting_account_types at ON coa.account_type_id = at.id WHERE at.name_en = 'Revenue' AND item.type = 'credit') -
            (SELECT COALESCE(SUM(item.amount), 0) FROM accounting_entry_items item JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id JOIN accounting_account_types at ON coa.account_type_id = at.id WHERE at.name_en = 'Expense' AND item.type = 'debit')
        AS total_net_profit
    ";
    $available_profit = (float)$db->query($all_time_profit_sql)->fetchColumn();
    
    if ($total_distribution_amount > $available_profit) {
        throw new Exception('المبلغ الموزع يتجاوز صافي الربح المتاح. الربح المتاح: ' . number_format($available_profit, 2));
    }

    // 2. Get necessary Account IDs from settings or Chart of Accounts
    // IMPORTANT: You must have these accounts in your chart_of_accounts table.
    // Let's assume you have an account for 'Retained Earnings' and a parent account for 'Partners Liabilities'
    $retained_earnings_account = $db->query("SELECT id FROM accounting_charts_of_accounts WHERE account_name_en = 'Retained Earnings' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    // This is the general account for money owed to partners.
    $partners_liability_account = $db->query("SELECT id FROM accounting_charts_of_accounts WHERE account_name_en LIKE 'Partners Current Account%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$retained_earnings_account || !$partners_liability_account) {
        throw new Exception('الحسابات اللازمة (الأرباح المبقاة / جاري الشركاء) غير موجودة في شجرة الحسابات.');
    }
    $retained_earnings_account_id = $retained_earnings_account['id'];
    $partners_liability_account_id = $partners_liability_account['id'];


    // 3. Create the main Journal Entry
    $stmt = $db->prepare("INSERT INTO accounting_journal_entries (entry_date, description, created_by) VALUES (?, ?, ?)");
    $description = 'إثبات توزيع أرباح بتاريخ ' . $entry_date;
    $stmt->execute([$entry_date, $description, $_SESSION['admin_id']]);
    $entry_id = $db->lastInsertId();

    // 4. Create the DEBIT entry item (for the total amount)
    $stmt = $db->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount, description) VALUES (?, ?, 'debit', ?, ?)");
    $stmt->execute([$entry_id, $retained_earnings_account_id, $total_distribution_amount, 'تخفيض الأرباح المبقاة مقابل توزيع الشركاء']);

    // 5. Create CREDIT entry items (one for each partner)
    $stmt = $db->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount, description) VALUES (?, ?, 'credit', ?, ?)");
    foreach ($distributions as $partner_id => $amount) {
        $amount = (float)$amount;
        if ($amount > 0) {
            $partner_name = $db->query("SELECT name FROM partners WHERE id = $partner_id")->fetchColumn();
            $item_description = 'حصة الشريك: ' . htmlspecialchars($partner_name);
            // Here, we credit the general "Partners' Current Account".
            // For more detailed accounting, you could create a sub-account for each partner.
            $stmt->execute([$entry_id, $partners_liability_account_id, $amount, $item_description]);
        }
    }
    
    // 6. Commit the transaction
    $db->commit();
    $_SESSION['success'] = 'تم حفظ توزيع الأرباح وتوليد القيد المحاسبي بنجاح!';

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = 'فشل العملية: ' . $e->getMessage();
}

// 7. Redirect back to the dashboard
header('Location: accounting_dashboard.php?tab=profit_distribution');
exit;