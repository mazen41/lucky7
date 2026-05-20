<?php
// /models/AccountingModel.php

class AccountingModel
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // =================================================================
    // DATA FETCHING METHODS
    // =================================================================

    public function getProfitAndLossData($startDate, $endDate)
    {
        $data = [
            'revenues' => [],
            'expenses' => [],
            'total_revenue' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
        ];
        $sql_rev = "SELECT coa.account_name_ar, COALESCE(SUM(CASE WHEN item.type = 'credit' THEN item.amount ELSE -item.amount END), 0) as balance FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id WHERE coa.account_type_id = 4 AND entry.entry_date BETWEEN ? AND ? GROUP BY coa.id HAVING balance != 0";
        $stmt_rev = $this->conn->prepare($sql_rev);
        $stmt_rev->execute([$startDate, $endDate]);
        $revenues = $stmt_rev->fetchAll(PDO::FETCH_ASSOC);
        $total_revenue = 0;
        foreach ($revenues as $row) {
            $total_revenue += $row['balance'];
        }
        $data['revenues'] = $revenues;
        $data['total_revenue'] = $total_revenue;
        $sql_exp = "SELECT coa.account_name_ar, COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END), 0) as balance FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id WHERE coa.account_type_id = 5 AND entry.entry_date BETWEEN ? AND ? GROUP BY coa.id HAVING balance != 0";
        $stmt_exp = $this->conn->prepare($sql_exp);
        $stmt_exp->execute([$startDate, $endDate]);
        $expenses = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
        $total_expense = 0;
        foreach ($expenses as $row) {
            $total_expense += $row['balance'];
        }
        $data['expenses'] = $expenses;
        $data['total_expense'] = $total_expense;
        $data['net_profit'] = $data['total_revenue'] - $data['total_expense'];
        return $data;
    }

    public function getCashFlowData($startDate, $endDate)
    {
        $settings = $this->getAccountingSettings();
        $account_ids = array_filter([$settings['default_cash_account_id'] ?? 0, $settings['default_card_account_id'] ?? 0, $settings['default_transfer_account_id'] ?? 0]);
        if (empty($account_ids)) {
            return ['opening_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'closing_balance' => 0, 'net_movement' => 0];
        }
        $ids_placeholder = implode(',', array_fill(0, count($account_ids), '?'));
        $sql_open = "SELECT COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END), 0) FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id WHERE item.account_id IN ($ids_placeholder) AND entry.entry_date < ?";
        $params_open = array_merge($account_ids, [$startDate]);
        $stmt_open = $this->conn->prepare($sql_open);
        $stmt_open->execute($params_open);
        $opening_balance = (float) $stmt_open->fetchColumn();
        $sql_period = "SELECT COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) as total_in, COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) as total_out FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id WHERE item.account_id IN ($ids_placeholder) AND entry.entry_date BETWEEN ? AND ?";
        $params_period = array_merge($account_ids, [$startDate, $endDate]);
        $stmt_period = $this->conn->prepare($sql_period);
        $stmt_period->execute($params_period);
        $period_data = $stmt_period->fetch(PDO::FETCH_ASSOC);
        $total_in = (float) $period_data['total_in'];
        $total_out = (float) $period_data['total_out'];
        $closing_balance = $opening_balance + $total_in - $total_out;
        return ['opening_balance' => $opening_balance, 'total_in' => $total_in, 'total_out' => $total_out, 'closing_balance' => $closing_balance, 'net_movement' => $total_in - $total_out];
    }

    public function getTrialBalanceData($startDate, $endDate)
    {
        $sql = "SELECT coa.account_code, coa.account_name_ar, at.normal_balance, COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE 0 END), 0) AS total_debit, COALESCE(SUM(CASE WHEN item.type = 'credit' THEN item.amount ELSE 0 END), 0) AS total_credit FROM accounting_charts_of_accounts coa JOIN accounting_account_types at ON coa.account_type_id = at.id LEFT JOIN accounting_entry_items item ON coa.id = item.account_id LEFT JOIN accounting_journal_entries entry ON item.entry_id = entry.id AND entry.entry_date BETWEEN ? AND ? GROUP BY coa.id HAVING total_debit > 0 OR total_credit > 0 ORDER BY coa.account_code ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChartData($startDate, $endDate)
    {
        $sql = "SELECT DATE_FORMAT(entry.entry_date, '%Y-%m') AS month, SUM(CASE WHEN at.name_en = 'Revenue' THEN (CASE WHEN item.type = 'credit' THEN item.amount ELSE -item.amount END) ELSE 0 END) AS monthly_revenue, SUM(CASE WHEN at.name_en = 'Expense' THEN (CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END) ELSE 0 END) AS monthly_expense FROM accounting_journal_entries entry JOIN accounting_entry_items item ON entry.id = item.entry_id JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id JOIN accounting_account_types at ON coa.account_type_id = at.id WHERE entry.entry_date BETWEEN ? AND ? AND at.name_en IN ('Revenue', 'Expense') GROUP BY month ORDER BY month ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpenseBreakdownForPieChart($startDate, $endDate)
    {
        $sql = "SELECT coa.account_name_ar AS category, COALESCE(SUM(CASE WHEN item.type = 'debit' THEN item.amount ELSE -item.amount END), 0) AS total_amount FROM accounting_entry_items item JOIN accounting_journal_entries entry ON item.entry_id = entry.id JOIN accounting_charts_of_accounts coa ON item.account_id = coa.id WHERE coa.account_type_id = 5 AND entry.entry_date BETWEEN ? AND ? GROUP BY coa.account_name_ar HAVING total_amount > 0 ORDER BY total_amount DESC LIMIT 5";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJournalEntries($startDate, $endDate, $page = 1, $limit = 15)
    {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT e.id, e.entry_date, e.description, e.source, COALESCE(SUM(CASE WHEN i.type = 'debit' THEN i.amount END), 0) as total_debit, COALESCE(SUM(CASE WHEN i.type = 'credit' THEN i.amount END), 0) as total_credit FROM accounting_journal_entries e JOIN accounting_entry_items i ON e.id = i.entry_id WHERE e.entry_date BETWEEN :startDate AND :endDate GROUP BY e.id ORDER BY e.entry_date DESC, e.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':startDate', $startDate);
        $stmt->bindValue(':endDate', $endDate);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countJournalEntries($startDate, $endDate)
    {
        $sql = "SELECT COUNT(id) FROM accounting_journal_entries WHERE entry_date BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn();
    }

    /**
     * NEW: Fetches a single journal entry with its items for editing.
     */
    public function getJournalEntryDetails($entryId)
    {
        $sql_entry = "SELECT id, entry_date, description FROM accounting_journal_entries WHERE id = ?";
        $stmt_entry = $this->conn->prepare($sql_entry);
        $stmt_entry->execute([$entryId]);
        $entry = $stmt_entry->fetch(PDO::FETCH_ASSOC);

        if (!$entry)
            return null;

        $sql_items = "SELECT account_id, type, amount FROM accounting_entry_items WHERE entry_id = ?";
        $stmt_items = $this->conn->prepare($sql_items);
        $stmt_items->execute([$entryId]);
        $entry['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        return $entry;
    }

    public function getAccountsList()
    {
        return $this->conn->query("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE is_active = 1 ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAccountsForCOATab()
    {
        return $this->conn->query("SELECT coa.*, at.name_ar AS type_name_ar FROM accounting_charts_of_accounts coa JOIN accounting_account_types at ON coa.account_type_id = at.id ORDER BY coa.account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccountTypes()
    {
        return $this->conn->query("SELECT * FROM accounting_account_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccountingSettings()
    {
        $settings = [];
        $stmt = $this->conn->query("SELECT setting_key, setting_value FROM accounting_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    // =================================================================
    // DATA MODIFICATION METHODS (ACTIONS)
    // =================================================================

    public function manageAccount($data)
    {
        if (!empty($data['account_id'])) {
            $stmt = $this->conn->prepare("UPDATE accounting_charts_of_accounts SET account_code = ?, account_name_ar = ?, account_name_en = ?, account_type_id = ? WHERE id = ?");
            $stmt->execute([$data['account_code'], $data['account_name_ar'], $data['account_name_en'], $data['account_type_id'], $data['account_id']]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO accounting_charts_of_accounts (account_code, account_name_ar, account_name_en, account_type_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['account_code'], $data['account_name_ar'], $data['account_name_en'], $data['account_type_id']]);
        }
    }

    public function deleteAccount($accountId)
    {
        $stmt_check = $this->conn->prepare("SELECT COUNT(id) FROM accounting_entry_items WHERE account_id = ?");
        $stmt_check->execute([$accountId]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("لا يمكن حذف الحساب لوجود حركات مرتبطة به.");
        }
        $stmt_delete = $this->conn->prepare("DELETE FROM accounting_charts_of_accounts WHERE id = ?");
        $stmt_delete->execute([$accountId]);
    }

    public function saveSettings($settings_to_save)
    {
        $this->conn->beginTransaction();
        $stmt = $this->conn->prepare("INSERT INTO accounting_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
        foreach ($settings_to_save as $key => $value) {
            if (!empty($value)) {
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
        }
        $this->conn->commit();
    }

    private function processAndValidateEntryItems($postData)
    {
        $accountIds = $postData['account_id'] ?? [];
        $debits = $postData['debit'] ?? [];
        $credits = $postData['credit'] ?? [];
        $items = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accountIds as $index => $accountId) {
            if (empty($accountId))
                continue;
            $debit = (float) ($debits[$index] ?? 0);
            $credit = (float) ($credits[$index] ?? 0);
            if ($debit > 0) {
                $items[] = ['account_id' => $accountId, 'type' => 'debit', 'amount' => $debit];
                $totalDebit += $debit;
            } elseif ($credit > 0) {
                $items[] = ['account_id' => $accountId, 'type' => 'credit', 'amount' => $credit];
                $totalCredit += $credit;
            }
        }
        if (empty($items))
            throw new Exception("لا توجد بنود في القيد.");
        if (abs($totalDebit - $totalCredit) > 0.01)
            throw new Exception("القيد غير متوازن.");
        if (empty($postData['description']))
            throw new Exception("الوصف لا يمكن أن يكون فارغاً.");

        return $items;
    }

    public function createManualJournalEntry($postData, $adminId)
    {
        $this->conn->beginTransaction();
        $items = $this->processAndValidateEntryItems($postData);
        $entryDate = $postData['entry_date'] ?? date('Y-m-d');
        $description = $postData['description'] ?? 'Manual journal entry';
        $entry_stmt = $this->conn->prepare("INSERT INTO accounting_journal_entries (entry_date, description, source, created_by_id) VALUES (?, ?, 'manual_entry', ?)");
        $entry_stmt->execute([$entryDate, $description, $adminId]);
        $entryId = $this->conn->lastInsertId();
        $item_stmt = $this->conn->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $item_stmt->execute([$entryId, $item['account_id'], $item['type'], $item['amount']]);
        }
        $this->conn->commit();
        return $entryId;
    }

    /**
     * NEW: Updates an existing manual journal entry.
     */
    public function updateManualJournalEntry($entryId, $postData, $adminId)
    {
        if (empty($entryId))
            throw new Exception("معرف القيد غير موجود.");

        $this->conn->beginTransaction();

        $items = $this->processAndValidateEntryItems($postData);
        $entryDate = $postData['entry_date'] ?? date('Y-m-d');
        $description = $postData['description'] ?? 'Updated manual entry';

        // Update main entry
        $entry_stmt = $this->conn->prepare("UPDATE accounting_journal_entries SET entry_date = ?, description = ? WHERE id = ?");
        $entry_stmt->execute([$entryDate, $description, $entryId]);

        // Delete old items
        $delete_stmt = $this->conn->prepare("DELETE FROM accounting_entry_items WHERE entry_id = ?");
        $delete_stmt->execute([$entryId]);

        // Insert new items
        $item_stmt = $this->conn->prepare("INSERT INTO accounting_entry_items (entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $item_stmt->execute([$entryId, $item['account_id'], $item['type'], $item['amount']]);
        }

        $this->conn->commit();
        return $entryId;
    }

    /**
     * NEW: Deletes a journal entry and its items.
     */
    /**
     * Deletes a journal entry and its associated source document (invoice or expense).
     * This ensures no orphaned records are left in the database.
     *
     * @param int $entryId The ID of the journal entry to delete.
     * @throws Exception If the entry ID is invalid or if a database error occurs.
     */
    public function deleteJournalEntry($entryId)
    {
        if (empty($entryId)) {
            throw new Exception("معرف القيد غير موجود.");
        }

        $this->conn->beginTransaction();

        try {
            // Step 1: Find the journal entry and its source reference
            $stmt_find = $this->conn->prepare(
                "SELECT reference_id, reference_type FROM accounting_journal_entries WHERE id = ?"
            );
            $stmt_find->execute([$entryId]);
            $entryInfo = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if (!$entryInfo) {
                throw new Exception("القيد المحاسبي بالمعرف {$entryId} غير موجود.");
            }

            // Step 2: If a source document is linked, delete it first
            if (!empty($entryInfo['reference_id']) && !empty($entryInfo['reference_type'])) {
                $refId = $entryInfo['reference_id'];
                $refType = $entryInfo['reference_type'];

                switch ($refType) {
                    case 'invoice':
                    case 'pos_invoice':
                    case 'invoice_return':
                        $this->_deleteInvoice($refId);
                        break;

                    case 'expense':
                        $this->_deleteExpense($refId);
                        break;

                    // Add other cases here if you have more reference types
                    // default:
                    //     // Optional: log a warning if an unknown reference type is found
                    //     break;
                }
            }

            // Step 3: Delete the journal entry items (debits/credits)
            $stmt_items = $this->conn->prepare("DELETE FROM accounting_entry_items WHERE entry_id = ?");
            $stmt_items->execute([$entryId]);

            // Step 4: Delete the main journal entry record
            $stmt_entry = $this->conn->prepare("DELETE FROM accounting_journal_entries WHERE id = ?");
            $stmt_entry->execute([$entryId]);

            // Step 5: Commit the transaction if everything was successful
            $this->conn->commit();

        } catch (Exception $e) {
            // If any step fails, roll back the entire transaction
            $this->conn->rollBack();
            // Re-throw the exception to notify the caller of the failure
            throw new Exception("فشل حذف القيد: " . $e->getMessage());
        }
    }

    /**
     * PRIVATE HELPER: Deletes an invoice and all its related data, including performance metrics.
     * This is designed to be called from within a transaction.
     *
     * @param int $invoiceId The ID of the invoice to delete.
     */
    private function _deleteInvoice($invoiceId)
    {
        // Get the full invoice details first to determine the date and type (sale/return)
        $stmt_invoice = $this->conn->prepare("SELECT is_return, created_at FROM invoices WHERE id = ?");
        $stmt_invoice->execute([$invoiceId]);
        $invoice_details = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

        if (!$invoice_details) {
            // If invoice doesn't exist, we can just skip this part
            return;
        }

        // Fetch all items to process stock and performance data
        $stmt_items = $this->conn->prepare(
            "SELECT item_id, item_type, quantity, specialist_id, total_price FROM invoice_items WHERE invoice_id = ?"
        );
        $stmt_items->execute([$invoiceId]);
        $items_to_process = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Prepare statements for stock and performance updates
        $stock_stmt = $this->conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $perf_stmt = $this->conn->prepare(
            "UPDATE hr_performance SET service_count = service_count - ?, revenue_sum = revenue_sum - ? WHERE employee_id = ? AND month = ?"
        );

        // Determine the month for the performance record (e.g., '2023-10')
        $invoice_month = date('Y-m', strtotime($invoice_details['created_at']));

        foreach ($items_to_process as $item) {
            // 1. Adjust product stock
            if ($item['item_type'] === 'product' && !empty($item['item_id'])) {
                // Reversing the transaction means adding stock back for a sale, 
                // and removing stock for a return (since its quantity is negative).
                $quantity_adjustment = $item['quantity'] * ($invoice_details['is_return'] ? -1 : 1);
                if ($quantity_adjustment != 0) {
                    $stock_stmt->execute([$quantity_adjustment, $item['item_id']]);
                }
            }

            // 2. Reverse specialist performance data
            // This should only apply to original sale invoices, not returns
            if (!$invoice_details['is_return'] && $item['item_type'] === 'service' && !empty($item['specialist_id'])) {
                $perf_stmt->execute([
                    (int) $item['quantity'],
                    (float) $item['total_price'],
                    (int) $item['specialist_id'],
                    $invoice_month
                ]);
            }
        }

        // 3. Delete related records first to avoid foreign key constraint errors
        $this->conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
        $this->conn->prepare("DELETE FROM payments WHERE invoice_id = ?")->execute([$invoiceId]);

        // 4. Finally, delete the main invoice record
        $this->conn->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
    }
    /**
     * PRIVATE HELPER: Deletes an expense and all its related data.
     * This is designed to be called from within a transaction.
     *
     * @param int $expenseId The ID of the expense to delete.
     */
    private function _deleteExpense($expenseId)
    {
        // If you have related tables like 'expense_items' or 'expense_attachments', delete from them here first.
        // Example: $this->conn->prepare("DELETE FROM expense_items WHERE expense_id = ?")->execute([$expenseId]);

        // Delete the main expense record
        // IMPORTANT: Make sure your expenses table is actually named 'expenses'.
        $this->conn->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expenseId]);
    }
}
?>