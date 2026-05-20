<?php
// =================================================================
// MAI SALON - PROCESS OPENING ENTRY
// This file handles the backend logic for saving the opening entry.
// =================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- SETUP AND SECURITY ---
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'accounting_functions.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();
$admin = getCurrentAdmin();

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If accessed directly, redirect to the dashboard
    header('Location: accounting_hub.php?tab=dashboard');
    exit;
}

try {
    // --- OPENING ENTRY LOGIC ---
    $entry_date = $_POST['entry_date'];
    $account_ids = $_POST['account_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];

    if (empty($entry_date) || empty($account_ids)) {
        throw new Exception('الرجاء إدخال التاريخ واختيار حساب واحد على الأقل.');
    }

    $total_debit = 0;
    $total_credit = 0;
    $items = [];

    foreach ($account_ids as $index => $acc_id) {
        if (empty($acc_id)) continue;
        $debit_val = floatval($debits[$index] ?? 0);
        $credit_val = floatval($credits[$index] ?? 0);

        if ($debit_val < 0 || $credit_val < 0) {
            throw new Exception('لا يمكن أن تكون المبالغ سالبة.');
        }
        if ($debit_val > 0 && $credit_val > 0) {
            throw new Exception('لا يمكن إدخال مبلغ مدين ودائن في نفس السطر.');
        }

        if ($debit_val > 0) {
            $items[] = ['account_id' => $acc_id, 'type' => 'debit', 'amount' => $debit_val];
            $total_debit += $debit_val;
        } elseif ($credit_val > 0) {
            $items[] = ['account_id' => $acc_id, 'type' => 'credit', 'amount' => $credit_val];
            $total_credit += $credit_val;
        }
    }

    if (abs(round($total_debit, 2) - round($total_credit, 2)) > 0.01) {
        throw new Exception('القيد غير متوازن! يجب أن يتساوى إجمالي المدين مع إجمالي الدائن.');
    }

    if (empty($items) || $total_debit == 0) {
        throw new Exception('الرجاء إدخال بعض الأرصدة والتأكد من أنها ليست صفراً.');
    }
    
    // --- DATABASE INSERTION ---
    $db->beginTransaction();
    createJournalEntry($db, $entry_date, 'قيد افتتاحي للنظام', $items, null, null, $admin['id']);
    $db->commit();

    // --- SUCCESS REDIRECT ---
    $_SESSION['success'] = 'تم تسجيل القيد الافتتاحي بنجاح!';
    // As requested, redirect to the dashboard (trial_balance tab is also a good option)
    header('Location: accounting_hub.php?tab=dashboard');
    exit;

} catch (Exception $e) {
    // --- ERROR HANDLING ---
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    // Set error message and redirect back to the opening entry form
    $_SESSION['error'] = 'فشل العملية: ' . $e->getMessage();
    header('Location: accounting_hub.php?tab=opening_entry');
    exit;
}
?> 