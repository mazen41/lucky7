<?php
// Make sure this file is included from a script that has already established a DB connection.

if (!function_exists('getAccountingSettings')) {
    function getAccountingSettings($db) {
        $settings = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM accounting_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Could not fetch accounting settings: " . $e->getMessage());
        }
        return $settings;
    }
}

if (!function_exists('getOrCreateExpenseAccount')) {
    function getOrCreateExpenseAccount($db, $categoryName) {
        $stmt = $db->prepare("SELECT id FROM accounting_charts_of_accounts WHERE account_name_ar = ? AND account_type_id = 5");
        $stmt->execute([$categoryName]);
        $accountId = $stmt->fetchColumn();
        if ($accountId) {
            return $accountId;
        } else {
            $code_stmt = $db->query("SELECT MAX(CAST(account_code AS UNSIGNED)) + 1 FROM accounting_charts_of_accounts WHERE account_code LIKE '5%'");
            $next_code = $code_stmt->fetchColumn() ?? 5001;
            $stmt = $db->prepare("INSERT INTO accounting_charts_of_accounts (account_code, account_name_ar, account_type_id) VALUES (?, ?, 5)");
            $stmt->execute([$next_code, $categoryName]);
            return $db->lastInsertId();
        }
    }
}


// =================================================================
// =================== START OF THE CORRECTED FUNCTION =============
// =================================================================
if (!function_exists('createJournalEntry')) {
    /**
     * Creates a balanced journal entry in the accounting system using named placeholders for safety.
     *
     * @param PDO $db The database connection object.
     * @param string $date The date of the entry (e.g., '2025-11-21').
     * @param string $description A description of the journal entry.
     * @param array $items An array of entry items, each with 'account_id', 'type' ('debit'/'credit'), and 'amount'.
     * @param int|null $refId An optional reference ID (e.g., invoice_id, expense_id).
     * @param string|null $refType An optional reference type (e.g., 'invoice', 'expense').
     * @param int|null $userId The ID of the user creating the entry.
     * @return int The ID of the newly created journal entry.
     * @throws Exception if the journal entry is not balanced.
     */
    function createJournalEntry($db, $date, $description, $items, $refId = null, $refType = null, $userId = null) {
        $totalDebits = 0;
        $totalCredits = 0;
        foreach ($items as $item) {
            // Ensure amount is a valid float
            $amount = round(floatval($item['amount']), 4);
            if ($item['type'] === 'debit') {
                $totalDebits += $amount;
            } else {
                $totalCredits += $amount;
            }
        }

        // Check if the entry is balanced to avoid accounting errors
        if (abs($totalDebits - $totalCredits) > 0.01) {
            // Provide a detailed error message for easier debugging
            throw new Exception("Journal entry is not balanced. Total Debits: {$totalDebits}, Total Credits: {$totalCredits}. Description: '{$description}'");
        }
        
        // ** THE FIX IS HERE: Using named placeholders (e.g., :entry_date) instead of positional '?' **
        // This makes the query much safer and easier to read. It completely eliminates "Invalid parameter number" errors.
        $sql = "INSERT INTO accounting_journal_entries 
                    (entry_date, description, reference_id, reference_type, user_id, created_at) 
                VALUES 
                    (:entry_date, :description, :reference_id, :reference_type, :user_id, NOW())";
        
        $stmt = $db->prepare($sql);

        // Bind each parameter to its named placeholder. This is unambiguous.
        $stmt->execute([
            ':entry_date' => $date,
            ':description' => $description,
            ':reference_id' => $refId,
            ':reference_type' => $refType,
            ':user_id' => $userId
        ]);
        
        $entryId = $db->lastInsertId();
        
        // The rest of the function remains the same as it was correct.
        $itemStmt = $db->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
             // Only insert items with a non-zero amount to keep the table clean.
             if (floatval($item['amount']) > 0) {
                $itemStmt->execute([$entryId, $item['account_id'], $item['type'], $item['amount']]);
            }
        }
        return $entryId;
    }
}
// =================================================================
// =================== END OF THE CORRECTED FUNCTION ===============
// =================================================================


// =================================================================
// =================== NEW FUNCTION FOR INCOME STATEMENT ===========
// =================================================================
if (!function_exists('getIncomeStatementData')) {
    function getIncomeStatementData($db, $startDate, $endDate) {
        $data = [
            'revenues' => [
                'cash' => 0,
                'card' => 0,
                'transfer' => 0,
                'total' => 0,
            ],
            'expenses' => [],
            'total_expenses' => 0,
            'net_profit' => 0,
            'profit_distribution_1' => 0, // Placeholder
            'profit_distribution_2' => 0, // Placeholder
            'retained_earnings_start' => 0, // Placeholder
            'retained_earnings_end' => 0,   // Placeholder
        ];

        // 1. Calculate detailed revenues from invoices table
        $rev_sql = "SELECT 
                        SUM(cash_amount) as total_cash, 
                        SUM(card_amount) as total_card,
                        SUM(transfer_amount) as total_transfer
                    FROM invoices 
                    WHERE DATE(created_at) BETWEEN ? AND ?";
        $rev_stmt = $db->prepare($rev_sql);
        $rev_stmt->execute([$startDate, $endDate]);
        $revenue_totals = $rev_stmt->fetch(PDO::FETCH_ASSOC);
        
        $data['revenues']['cash'] = (float)($revenue_totals['total_cash'] ?? 0);
        $data['revenues']['card'] = (float)($revenue_totals['total_card'] ?? 0);
        $data['revenues']['transfer'] = (float)($revenue_totals['total_transfer'] ?? 0);
        $data['revenues']['total'] = $data['revenues']['cash'] + $data['revenues']['card'] + $data['revenues']['transfer'];

        // 2. Calculate detailed expenses from expenses table
        $exp_sql = "SELECT expense_category, SUM(amount) as total_amount
                    FROM expenses
                    WHERE expense_date BETWEEN ? AND ?
                    GROUP BY expense_category
                    ORDER BY expense_category";
        $exp_stmt = $db->prepare($exp_sql);
        $exp_stmt->execute([$startDate, $endDate]);
        $expenses_list = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data['expenses'] = $expenses_list;
        $data['total_expenses'] = array_sum(array_column($expenses_list, 'total_amount'));

        // 3. Calculate Net Profit
        $data['net_profit'] = $data['revenues']['total'] - $data['total_expenses'];

        // Note: Profit distribution and retained earnings are complex accounting closing entries.
        // These are left as placeholders for now.
        $data['retained_earnings_end'] = $data['retained_earnings_start'] + $data['net_profit'] - ($data['profit_distribution_1'] + $data['profit_distribution_2']);

        return $data;
    }
}