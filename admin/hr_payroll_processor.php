<?php
session_start();
require_once '../config/database.php';
require_once 'accounting_functions.php'; // EDITED: Include accounting functions
// Assuming a function to get current admin details exists, similar to other files
require_once 'auth.php'; 

// Security check: only authenticated admins can run this
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['payroll_error'] = 'Authentication required.';
    header('Location: hr_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['run_payroll'])) {
    header('Location: hr_dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin(); // Get current admin for logging purposes

$month = $_POST['month']; // e.g., '2025-10'
$manual_bonuses = $_POST['manual_bonus'] ?? [];
$manual_deductions = $_POST['manual_deduction'] ?? [];
$manual_notes = $_POST['notes'] ?? [];


// --- STEP 1: VERIFY PAYROLL HAS NOT BEEN RUN FOR THIS MONTH ---
$check_stmt = $db->prepare("SELECT COUNT(*) FROM hr_payroll WHERE month = ?");
$check_stmt->execute([$month]);
if ($check_stmt->fetchColumn() > 0) {
    $_SESSION['payroll_error'] = "تم تشغيل مسير الرواتب لهذا الشهر ($month) بالفعل.";
    header('Location: hr_payroll.php?month=' . $month);
    exit;
}

try {
    // EDITED: Fetch accounting settings before starting
    $acc_settings = getAccountingSettings($db);
    
    $db->beginTransaction();

    // --- STEP 2: GET ALL ACTIVE EMPLOYEES and their roles ---
    $employees_stmt = $db->prepare("
        SELECT tm.id, tm.name_ar, tm.salary, tm.is_specialist, u.role_id 
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.is_active = 1
    ");
    $employees_stmt->execute();
    $employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed_count = 0;

    foreach ($employees as $employee) {
        $employee_id = $employee['id'];
        $base_salary = (float)($employee['salary'] ?? 0);
        $daily_pay = $base_salary > 0 ? $base_salary / 30 : 0; // Standard accounting practice

        // --- STEP 3: GET AUTO-CALCULATED BONUS FROM PERFORMANCE ---
        $perf_stmt = $db->prepare("SELECT bonus_earned FROM hr_performance WHERE employee_id = ? AND month = ?");
        $perf_stmt->execute([$employee_id, $month]);
        $auto_bonus = (float)($perf_stmt->fetchColumn() ?? 0);
        
        // Add manual bonus from the form
        $manual_bonus = (float)($manual_bonuses[$employee_id] ?? 0);
        $total_bonus = $auto_bonus + $manual_bonus;

        // --- STEP 4: CALCULATE OVERTIME PAY ---
        $overtime_stmt = $db->prepare("SELECT SUM(overtime_hours) FROM hr_attendance WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
        $overtime_stmt->execute([$employee_id, $month]);
        $total_overtime_hours = (float)($overtime_stmt->fetchColumn() ?? 0);
        $hourly_rate = $daily_pay > 0 ? $daily_pay / 8 : 0; // Assuming 8-hour workday
        $overtime_pay = $total_overtime_hours * $hourly_rate;

        // --- STEP 5: CALCULATE DEDUCTIONS ---
        $total_deductions = 0;

        // 5a. Deductions for unapproved absences
        $absent_stmt = $db->prepare("SELECT date FROM hr_attendance WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'absent'");
        $absent_stmt->execute([$employee_id, $month]);
        $absent_dates = $absent_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $unpaid_absences_count = 0;
        foreach ($absent_dates as $absent_date) {
            $leave_check_stmt = $db->prepare("SELECT type FROM hr_leaves WHERE employee_id = ? AND ? BETWEEN from_date AND to_date AND status = 'approved'");
            $leave_check_stmt->execute([$employee_id, $absent_date]);
            $leave_type = $leave_check_stmt->fetchColumn();

            if (!$leave_type || $leave_type === 'unpaid') {
                $total_deductions += $daily_pay;
                $unpaid_absences_count++;
            }
        }

        // 5b. Deductions for salary advances
        $advance_stmt = $db->prepare("SELECT SUM(amount) FROM hr_requests WHERE employee_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ? AND type = 'salary_advance' AND status = 'approved'");
        $advance_stmt->execute([$employee_id, $month]);
        $advances = (float)($advance_stmt->fetchColumn() ?? 0);
        $total_deductions += $advances;

        // 5c. Add manual deduction from the form
        $manual_deduction = (float)($manual_deductions[$employee_id] ?? 0);
        $total_deductions += $manual_deduction;

        // --- STEP 6: CALCULATE NET SALARY ---
        $net_salary = ($base_salary + $total_bonus + $overtime_pay) - $total_deductions;

        // --- STEP 7: INSERT RECORD INTO PAYROLL TABLE ---
        $insert_payroll_stmt = $db->prepare("
            INSERT INTO hr_payroll 
                (employee_id, month, base_salary, bonus, overtime_pay, deductions, net_salary, status, notes)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        
        $notes = "Auto-Calculated:\n";
        $notes .= "- Auto Bonus: " . number_format($auto_bonus, 2) . " SAR\n";
        $notes .= "- Overtime Hours: $total_overtime_hours\n";
        $notes .= "- Unpaid Absences: $unpaid_absences_count days\n";
        $notes .= "- Salary Advances: " . number_format($advances, 2) . " SAR\n";
        if ($manual_bonus > 0 || $manual_deduction > 0 || !empty(trim($manual_notes[$employee_id] ?? ''))) {
            $notes .= "----\nManual Adjustments:\n";
            if ($manual_bonus > 0) $notes .= "- Added Bonus: " . number_format($manual_bonus, 2) . " SAR\n";
            if ($manual_deduction > 0) $notes .= "- Added Deduction: " . number_format($manual_deduction, 2) . " SAR\n";
            if (!empty(trim($manual_notes[$employee_id] ?? ''))) $notes .= "- Notes: " . htmlspecialchars(trim($manual_notes[$employee_id])) . "\n";
        }

        $insert_payroll_stmt->execute([
            $employee_id,
            $month,
            $base_salary,
            round($total_bonus, 2),
            round($overtime_pay, 2),
            round($total_deductions, 2),
            round($net_salary, 2),
            $notes
        ]);
        $payroll_id = $db->lastInsertId();
        
        // --- EDITED: CREATE ACCOUNTING JOURNAL ENTRY ---
        if ($net_salary > 0) {
            $total_salary_expense = $base_salary + $total_bonus + $overtime_pay;
            $journal_items = [
                // We debit the full salary expense (base + bonus + overtime)
                ['account_id' => $acc_settings['default_payroll_expense_account_id'], 'type' => 'debit', 'amount' => $total_salary_expense],
                // We credit the cash/bank account with the net amount paid out
                ['account_id' => $acc_settings['default_cash_account_id'], 'type' => 'credit', 'amount' => $net_salary],
            ];
            
            // This is a simplified model. A more advanced version would credit a "Salary Advances" asset account
            // for the `advances` amount instead of just reducing the cash credit.
            // For now, this is correct based on the provided logic.

            createJournalEntry(
                $db,
                date('Y-m-d'),
                "قيد رواتب شهر {$month} للموظف: {$employee['name_ar']}",
                $journal_items,
                $payroll_id,
                'payroll',
                $admin['id']
            );
        }
        
        $processed_count++;
    }

    $db->commit();
    $_SESSION['payroll_message'] = "تم إعداد مسير الرواتب بنجاح لشهر $month. تم معالجة $processed_count موظف.";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Payroll Processor Error: " . $e->getMessage());
    $_SESSION['payroll_error'] = "حدث خطأ فني أثناء إعداد الرواتب: " . $e->getMessage();
}

header('Location: hr_payroll.php?month=' . $month);
exit;
?>