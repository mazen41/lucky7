<?php
// --- START OF PHP LOGIC ---
// Prevent browser caching & enable error reporting
header("Cache-Control: no-cache, no-store, must-revalidate");
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'auth.php';
require_once 'accounting_functions.php';

checkAdminAuth();

// --- PERMISSION CHECK: Page Access ---
requirePermission('invoices.view');
// --- END PERMISSION CHECK ---

$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// --- START: EDITED AND FIXED HELPER FUNCTIONS ---

/**
 * Generates a unique, sequential invoice number for returns.
 * This is a more robust method that prevents race conditions.
 */
function generateReturnNumber($db, $prefix = 'RTN-')
{
    $date_prefix = $prefix . date('Ymd') . '-';
    // Find the highest invoice number with the specified prefix for today
    $stmt = $db->prepare("SELECT MAX(invoice_number) as last_invoice FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute([$date_prefix . '%']);
    $last_invoice = $stmt->fetchColumn();

    $next_num = 1;
    if ($last_invoice) {
        // If a return invoice exists for today, extract its sequence number and increment
        $last_num_str = substr($last_invoice, strlen($date_prefix));
        $last_num = (int) $last_num_str;
        $next_num = $last_num + 1;
    }

    // Return the new, formatted return number
    return $date_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Translates invoice status into a user-friendly display string and CSS class.
 */
function getInvoiceTypeDisplay($invoice)
{
    if ($invoice['is_return']) {
        return '<span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">مرتجع</span>';
    } else {
        $type = 'بيع';
        $payment_method = 'غير محدد';
        $class = 'bg-gray-100 text-gray-800';
        switch ($invoice['payment_method']) {
            case 'cash':
                $payment_method = 'نقدي';
                $class = 'bg-green-100 text-green-800';
                break;
            case 'card':
                $payment_method = 'بطاقة';
                $class = 'bg-blue-100 text-blue-800';
                break;
            case 'bank_transfer':
                $payment_method = 'تحويل';
                $class = 'bg-purple-100 text-purple-800';
                break;
            case 'mixed':
                $payment_method = 'مختلط';
                $class = 'bg-yellow-100 text-yellow-800';
                break;
        }
        return "<span class=\"px-3 py-1 text-xs font-semibold rounded-full {$class}\">{$type} / {$payment_method}</span>";
    }
}
// --- END: HELPER FUNCTIONS ---

if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['get_details', 'update_specialist'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $response = ['success' => false, 'message' => 'Invalid action.'];

    try {
        if ($action === 'get_details') {
            if (!hasPermission('invoices.view')) {
                throw new Exception('Unauthorized');
            }
            $invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$invoice_id) {
                throw new Exception('Invalid Invoice ID.');
            }

            $stmt_invoice = $db->prepare("SELECT i.*, c.name_ar as registered_customer_name, c.phone as customer_phone FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ?");
            $stmt_invoice->execute([$invoice_id]);
            $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('Invoice not found.');
            }

            $stmt_items = $db->prepare("
                SELECT ii.*, tm.name_ar AS specialist_name
                FROM invoice_items ii
                LEFT JOIN team_members tm ON ii.specialist_id = tm.id
                WHERE ii.invoice_id = ?
                ORDER BY ii.id ASC
            ");
            $stmt_items->execute([$invoice_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'data' => ['invoice' => $invoice, 'items' => $items]];
        } elseif ($action === 'update_specialist') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method.');
            }
            if (!hasPermission('invoices.edit')) {
                throw new Exception('Unauthorized to edit invoices.');
            }

            $item_id = filter_input(INPUT_POST, 'invoice_item_id', FILTER_VALIDATE_INT);
            $specialist_id = filter_input(INPUT_POST, 'specialist_id', FILTER_VALIDATE_INT);
            if (!$item_id) {
                throw new Exception('Invalid item ID.');
            }

            if ($specialist_id === false && !empty($_POST['specialist_id'])) {
                throw new Exception('Invalid specialist ID.');
            }

            $update_stmt = $db->prepare("UPDATE invoice_items SET specialist_id = ? WHERE id = ?");
            $update_stmt->execute([$specialist_id ?: null, $item_id]);

            $new_specialist_name = 'غير محدد';
            if ($specialist_id) {
                $name_stmt = $db->prepare("SELECT name_ar FROM team_members WHERE id = ?");
                $name_stmt->execute([$specialist_id]);
                $new_specialist_name = $name_stmt->fetchColumn() ?: 'غير محدد';
            }

            $response = ['success' => true, 'message' => 'تم تحديث الأخصائي بنجاح.', 'new_specialist_name' => $new_specialist_name];
        }
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit(); // Stop script execution for all AJAX requests
}

// --- FETCH DATA FOR PAGE DISPLAY ---
// Fetch Specialists for dropdowns
$specialists_stmt = $db->prepare("SELECT id, name_ar, name_en FROM team_members WHERE is_active = 1 AND is_specialist = 1 ORDER BY name_ar ASC");
$specialists_stmt->execute();
$all_specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_specialists_json = json_encode($all_specialists);

// Fetch Customers for Edit Modal dropdown
$customers_stmt = $db->prepare("SELECT id, name_ar, phone FROM customers ORDER BY name_ar ASC");
$customers_stmt->execute();
$all_customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_customers_json = json_encode($all_customers);

// --- FETCH ACCOUNTING SETTINGS & PAYMENT ACCOUNTS ---
$acc_settings = getAccountingSettings($db);
$payment_accounts = [];
$stmt = $db->prepare("SELECT id, account_code, account_name_ar FROM accounting_charts_of_accounts WHERE id IN (4, 5) OR (account_type_id = 1 AND (account_name_ar LIKE '%بنك%' OR account_name_ar LIKE '%كاش%' OR account_name_ar LIKE '%خزينة%')) AND is_active = 1");
$stmt->execute();
$payment_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- START: ALL POST ACTIONS (Full Page Reload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        $action = $_POST['action'] ?? '';
        $current_admin_id = $admin['id'];

        if (empty($action)) {
            throw new Exception("Action not specified.");
        }

        $sales_acc_id = $acc_settings['default_sales_account_id'] ?? 24;
        $debit_acc_id = $acc_settings['default_sales_return_account_id'] ?? $sales_acc_id;
        $vat_acc_id = $acc_settings['default_vat_account_id'] ?? 23;
        $cash_acc_id = $acc_settings['default_cash_account_id'] ?? 4;
        $card_acc_id = $acc_settings['default_card_account_id'] ?? 5;

        if ($action === 'update_invoice') {
            requirePermission('invoices.edit');

            // --- 1. GET ALL DATA FROM THE FORM ---
            $invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
            if (!$invoice_id) {
                throw new Exception("رقم الفاتورة غير صالح.");
            }

            // Get and validate invoice date
            $invoice_date = $_POST['invoice_date'] ?? null;
            if (empty($invoice_date) || !strtotime($invoice_date)) {
                throw new Exception("تاريخ الفاتورة المقدم غير صالح.");
            }
            $invoice_date_formatted = date('Y-m-d H:i:s', strtotime($invoice_date));


            $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $cash_amount = filter_input(INPUT_POST, 'cash_amount', FILTER_VALIDATE_FLOAT) ?: 0;
            $card_amount = filter_input(INPUT_POST, 'card_amount', FILTER_VALIDATE_FLOAT) ?: 0;
            $notes = trim($_POST['notes'] ?? '');
            $new_items = $_POST['items'] ?? [];

            // --- 2. FETCH ORIGINAL INVOICE AND ITEMS ---
            $stmt_orig_invoice = $db->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt_orig_invoice->execute([$invoice_id]);
            $original_invoice = $stmt_orig_invoice->fetch(PDO::FETCH_ASSOC);
            if (!$original_invoice) {
                throw new Exception("الفاتورة الأصلية غير موجودة.");
            }

            $stmt_orig_items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $stmt_orig_items->execute([$invoice_id]);
            $original_items = $stmt_orig_items->fetchAll(PDO::FETCH_ASSOC);

            // --- 3. REVERSE STOCK FOR ORIGINAL ITEMS ---
            $stock_stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($original_items as $item) {
                if ($item['item_type'] === 'product' && !empty($item['item_id'])) {
                    $stock_stmt->execute([$item['quantity'], $item['item_id']]);
                }
            }

            // --- 4. DELETE OLD ACCOUNTING ENTRIES ---
            $stmt_entry = $db->prepare("SELECT id FROM accounting_journal_entries WHERE reference_id = ? AND reference_type IN ('invoice', 'pos_invoice')");
            $stmt_entry->execute([$invoice_id]);
            $journal_entries = $stmt_entry->fetchAll(PDO::FETCH_COLUMN);
            if ($journal_entries) {
                $db->prepare("DELETE FROM accounting_entry_items WHERE entry_id IN (" . implode(',', $journal_entries) . ")")->execute();
                $db->prepare("DELETE FROM accounting_journal_entries WHERE id IN (" . implode(',', $journal_entries) . ")")->execute();
            }

            // --- 5. DELETE OLD INVOICE ITEMS ---
            $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoice_id]);

            // --- 6. CALCULATE NEW TOTALS AND PREPARE NEW ITEMS ---
            $new_subtotal = 0;
            $tax_rate = $original_invoice['tax_rate']; // Keep original tax rate

            $item_insert_stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, item_name, quantity, unit_price, total_price, specialist_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stock_update_stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

            foreach ($new_items as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $total = $qty * $price;
                $new_subtotal += $total;

                // Insert into invoice_items
                $item_insert_stmt->execute([
                    $invoice_id,
                    $item['item_type'] ?? 'service',
                    ($item['item_id'] && $item['item_id'] !== 'custom') ? $item['item_id'] : null,
                    trim($item['item_name']),
                    $qty,
                    $price,
                    $total,
                    !empty($item['specialist_id']) ? $item['specialist_id'] : null
                ]);

                // Update stock if it's a product
                if (($item['item_type'] ?? 'service') === 'product' && !empty($item['item_id']) && $item['item_id'] !== 'custom') {
                    $stock_update_stmt->execute([$qty, $item['item_id']]);
                }
            }

            $new_tax = round($new_subtotal * ($tax_rate / 100), 2);
            $new_total = $new_subtotal + $new_tax; // Assuming discount is not editable for now to simplify

            // --- 7. UPDATE THE MAIN INVOICE RECORD ---
            $update_invoice_stmt = $db->prepare("UPDATE invoices SET customer_id = ?, subtotal = ?, tax_amount = ?, total_amount = ?, payment_method = ?, cash_amount = ?, card_amount = ?, notes = ?, updated_at = NOW(), created_at = ? WHERE id = ?");
            $update_invoice_stmt->execute([
                $customer_id ?: null,
                $new_subtotal,
                $new_tax,
                $new_total,
                $payment_method,
                $payment_method === 'mixed' ? $cash_amount : ($payment_method === 'cash' ? $new_total : 0),
                $payment_method === 'mixed' ? $card_amount : ($payment_method === 'card' ? $new_total : 0),
                $notes,
                $invoice_date_formatted,
                $invoice_id
            ]);

            // --- 8. CREATE NEW JOURNAL ENTRY ---
            $journal_desc = "تحديث بيانات فاتورة #" . $original_invoice['invoice_number'];
            $journal_items = [];

            // Credits
            if ($new_subtotal > 0)
                $journal_items[] = ['account_id' => $sales_acc_id, 'type' => 'credit', 'amount' => $new_subtotal];
            if ($new_tax > 0)
                $journal_items[] = ['account_id' => $vat_acc_id, 'type' => 'credit', 'amount' => $new_tax];

            // Debits
            if ($payment_method === 'cash' && $new_total > 0) {
                $journal_items[] = ['account_id' => $cash_acc_id, 'type' => 'debit', 'amount' => $new_total];
            } elseif ($payment_method === 'card' && $new_total > 0) {
                $journal_items[] = ['account_id' => $card_acc_id, 'type' => 'debit', 'amount' => $new_total];
            } elseif ($payment_method === 'mixed') {
                if ($cash_amount > 0)
                    $journal_items[] = ['account_id' => $cash_acc_id, 'type' => 'debit', 'amount' => $cash_amount];
                if ($card_amount > 0)
                    $journal_items[] = ['account_id' => $card_acc_id, 'type' => 'debit', 'amount' => $card_amount];
            }

            createJournalEntry($db, date('Y-m-d', strtotime($invoice_date_formatted)), $journal_desc, $journal_items, $invoice_id, 'invoice', $current_admin_id);

            $_SESSION['flash_success'] = "تم تحديث الفاتورة #" . htmlspecialchars($original_invoice['invoice_number']) . " بنجاح.";
        } elseif ($action === 'create_item_return') {
            requirePermission('invoices.create_return');

            $original_invoice_id = filter_input(INPUT_POST, 'original_invoice_id', FILTER_VALIDATE_INT);
            $return_items = $_POST['return_items'] ?? [];
            $return_reason = trim($_POST['return_reason'] ?? 'N/A');
            $refund_from_account_id = filter_input(INPUT_POST, 'refund_from_account_id', FILTER_VALIDATE_INT);

            if (!$original_invoice_id || empty($return_items) || !$refund_from_account_id) {
                throw new Exception("بيانات المرتجع غير مكتملة. الرجاء تحديد الأصناف وحساب الدفع.");
            }

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? AND is_return = 0");
            $stmt->execute([$original_invoice_id]);
            $original_invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$original_invoice_id]);
            $original_invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$original_invoice || !$original_invoice_items) {
                throw new Exception("الفاتورة الأصلية غير موجودة.");
            }

            $return_total = 0;
            $return_subtotal = 0;
            $return_tax = 0;
            $items_to_return = [];
            $tax_rate_decimal = $original_invoice['tax_rate'] ? ($original_invoice['tax_rate'] / 100) : 0;

            foreach ($original_invoice_items as $original_item) {
                if (isset($return_items[$original_item['id']]) && $return_items[$original_item['id']] > 0) {
                    $return_qty = (int) $return_items[$original_item['id']];
                    if ($return_qty > $original_item['quantity']) {
                        throw new Exception("لا يمكن إرجاع كمية أكبر من الكمية المباعة للمنتج: " . $original_item['item_name']);
                    }

                    $item_subtotal = $return_qty * $original_item['unit_price'];
                    $return_subtotal += $item_subtotal;
                    $items_to_return[] = ['original_item' => $original_item, 'return_qty' => $return_qty, 'item_total' => $item_subtotal];
                }
            }

            if (empty($items_to_return)) {
                throw new Exception("لم يتم تحديد أي أصناف للإرجاع.");
            }

            $return_tax = $return_subtotal * $tax_rate_decimal;
            $return_total = $return_subtotal + $return_tax;

            $return_invoice_number = generateReturnNumber($db, 'RTN-');

            $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_id, total_amount, subtotal, tax_amount, tax_rate, status, payment_status, is_return, return_for_invoice_id, user_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'refunded', 'refunded', 1, ?, ?, ?, NOW())");
            $stmt->execute([$return_invoice_number, $original_invoice['customer_id'], -$return_total, -$return_subtotal, -$return_tax, $original_invoice['tax_rate'], $original_invoice_id, $current_admin_id, $return_reason]);
            $return_invoice_id = $db->lastInsertId();

            $item_stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stock_stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($items_to_return as $item) {
                $original = $item['original_item'];
                $item_stmt->execute([$return_invoice_id, $original['item_type'], $original['item_id'], $original['item_name'], -$item['return_qty'], $original['unit_price'], -$item['item_total']]);
                if ($original['item_type'] === 'product') {
                    $stock_stmt->execute([$item['return_qty'], $original['item_id']]);
                }
            }

            $journal_desc = "مرتجع أصناف من فاتورة #" . $original_invoice['invoice_number'];
            $journal_items = [['account_id' => $refund_from_account_id, 'type' => 'credit', 'amount' => $return_total]];
            if ($return_tax > 0) {
                $journal_items[] = ['account_id' => $debit_acc_id, 'type' => 'debit', 'amount' => $return_subtotal];
                $journal_items[] = ['account_id' => $vat_acc_id, 'type' => 'debit', 'amount' => $return_tax];
            } else {
                $journal_items[] = ['account_id' => $debit_acc_id, 'type' => 'debit', 'amount' => $return_total];
            }

            createJournalEntry($db, date('Y-m-d'), $journal_desc, $journal_items, $return_invoice_id, 'invoice_return', $current_admin_id);
            $_SESSION['flash_success'] = "تم إنشاء فاتورة مرتجع الأصناف رقم {$return_invoice_number} بنجاح.";
        } elseif ($action === 'create_value_return') {
            requirePermission('invoices.create_return');

            $original_invoice_id = filter_input(INPUT_POST, 'original_invoice_id', FILTER_VALIDATE_INT);
            $return_amount = filter_input(INPUT_POST, 'return_amount', FILTER_VALIDATE_FLOAT);
            $return_reason = trim($_POST['return_reason_value'] ?? 'N/A');
            $refund_from_account_id_val = filter_input(INPUT_POST, 'refund_from_account_id_value', FILTER_VALIDATE_INT);

            if (!$original_invoice_id || !$return_amount || $return_amount <= 0 || !$refund_from_account_id_val) {
                throw new Exception('الرجاء إدخال مبلغ صحيح للمرتجع وتحديد حساب الدفع.');
            }

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? AND is_return = 0");
            $stmt->execute([$original_invoice_id]);
            $original_invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original_invoice) {
                throw new Exception("الفاتورة الأصلية غير موجودة.");
            }
            if ($return_amount > $original_invoice['total_amount']) {
                throw new Exception("مبلغ المرتجع لا يمكن أن يكون أكبر من إجمالي الفاتورة الأصلية.");
            }

            $return_invoice_number = generateReturnNumber($db, 'RTN-VAL-');
            $stmt = $db->prepare("INSERT INTO invoices (invoice_number, customer_id, total_amount, status, payment_status, is_return, return_for_invoice_id, user_id, notes, created_at) VALUES (?, ?, ?, 'refunded', 'refunded', 1, ?, ?, ?, NOW())");
            $stmt->execute([$return_invoice_number, $original_invoice['customer_id'], -$return_amount, $original_invoice_id, $current_admin_id, $return_reason]);
            $return_invoice_id = $db->lastInsertId();

            $db->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_name, quantity, unit_price, total_price) VALUES (?, 'refund', 'مرتجع مبلغ جزئي', ?, ?, ?)")->execute([$return_invoice_id, -1, $return_amount, -$return_amount]);

            $journal_desc_val = "مرتجع مبلغ من فاتورة #" . $original_invoice['invoice_number'];
            $journal_items_val = [
                ['account_id' => $debit_acc_id, 'type' => 'debit', 'amount' => $return_amount],
                ['account_id' => $refund_from_account_id_val, 'type' => 'credit', 'amount' => $return_amount]
            ];
            createJournalEntry($db, date('Y-m-d'), $journal_desc_val, $journal_items_val, $return_invoice_id, 'invoice_return', $current_admin_id);
            $_SESSION['flash_success'] = "تم إنشاء فاتورة مرتجع بمبلغ " . number_format($return_amount, 2) . " بنجاح.";
        } elseif ($action === 'delete_invoice') {
            requirePermission('invoices.delete');

            $invoice_id_to_delete = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
            if (!$invoice_id_to_delete) {
                throw new Exception('رقم الفاتورة المقدم للحذف غير صالح.');
            }

            $stmt_invoice = $db->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt_invoice->execute([$invoice_id_to_delete]);
            $invoice_to_delete = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

            if (!$invoice_to_delete) {
                throw new Exception("الفاتورة غير موجودة.");
            }

            // First, handle accounting and stock reversals which need item data
            $stmt_items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $stmt_items->execute([$invoice_id_to_delete]);
            $items_to_delete = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            // Delete associated accounting journal entries
            $stmt_entry = $db->prepare("SELECT id FROM accounting_journal_entries WHERE reference_id = ? AND reference_type IN ('invoice', 'pos_invoice', 'invoice_return')");
            $stmt_entry->execute([$invoice_id_to_delete]);
            $journal_entries = $stmt_entry->fetchAll(PDO::FETCH_COLUMN);

            if ($journal_entries) {
                $db->prepare("DELETE FROM accounting_entry_items WHERE entry_id IN (" . implode(',', $journal_entries) . ")")->execute();
                $db->prepare("DELETE FROM accounting_journal_entries WHERE id IN (" . implode(',', $journal_entries) . ")")->execute();
            }

            // Prepare statements for stock and performance reversal
            $stock_stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");

            // --- START: NEW CODE to reverse HR performance data ---
            $perf_stmt = $db->prepare(
                "UPDATE hr_performance SET service_count = service_count - ?, revenue_sum = revenue_sum - ? WHERE employee_id = ? AND month = ?"
            );
            $invoice_month = date('Y-m', strtotime($invoice_to_delete['created_at']));
            // --- END: NEW CODE ---

            // Loop through items to reverse stock AND performance
            foreach ($items_to_delete as $item) {
                // Adjust stock for products
                if ($item['item_type'] === 'product' && !empty($item['item_id'])) {
                    // if it's a return invoice, quantity is negative, so adding a negative is subtracting stock.
                    // when deleting a return, we must add stock back.
                    $quantity_adjustment = $item['quantity'] * ($invoice_to_delete['is_return'] ? -1 : 1);
                    if ($quantity_adjustment != 0) {
                        $stock_stmt->execute([$quantity_adjustment, $item['item_id']]);
                    }
                }

                // --- START: NEW CODE to execute performance reversal ---
                // Only reverse performance for original sale invoices, not returns
                if (!$invoice_to_delete['is_return'] && $item['item_type'] === 'service' && !empty($item['specialist_id'])) {
                    $perf_stmt->execute([
                        (int) $item['quantity'],
                        (float) $item['total_price'],
                        (int) $item['specialist_id'],
                        $invoice_month
                    ]);
                }
                // --- END: NEW CODE ---
            }

            // Now, delete the records from the database
            $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoice_id_to_delete]);
            $db->prepare("DELETE FROM payments WHERE invoice_id = ?")->execute([$invoice_id_to_delete]); // Also delete related payments
            $db->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoice_id_to_delete]);

            $_SESSION['flash_success'] = "تم حذف الفاتورة #" . htmlspecialchars($invoice_to_delete['invoice_number']) . " وبياناتها المرتبطة بنجاح.";
        }
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = "فشل الإجراء: " . $e->getMessage();
    }
    header('Location: invoices.php' . '?' . http_build_query($_GET));
    exit();
}
// --- END: ALL POST ACTIONS ---


$print_settings_raw = $db->query("SELECT setting_key, setting_value, status FROM site_settings WHERE setting_key IN ('pos_receipt_header', 'tax_number', 'pos_receipt_footer', 'tax_rate')")->fetchAll(PDO::FETCH_ASSOC);
$print_settings = ['salonName' => 'MAI Salon', 'taxNumber' => '', 'footerText' => 'شكرا لزيارتكم'];
$tax_enabled = false;
foreach ($print_settings_raw as $setting) {
    if ($setting['setting_key'] === 'pos_receipt_header')
        $print_settings['salonName'] = $setting['setting_value'];
    elseif ($setting['setting_key'] === 'tax_number')
        $print_settings['taxNumber'] = $setting['setting_value'];
    elseif ($setting['setting_key'] === 'pos_receipt_footer')
        $print_settings['footerText'] = $setting['setting_value'];
    elseif ($setting['setting_key'] === 'tax_rate' && $setting['status'] === 'active')
        $tax_enabled = true;
}
$print_settings['isTaxEnabled'] = $tax_enabled;
$print_settings_json = json_encode($print_settings);

// --- START: DATA FETCHING WITH FILTERING AND PAGINATION ---
$items_per_page = 15;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Get filter values from GET parameters
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_invoice_number = trim($_GET['filter_invoice_number'] ?? '');
$filter_customer_name = trim($_GET['filter_customer_name'] ?? '');

$where_clauses = [];
$params = [];

// Filter by invoice type (sale/return)
if ($filter_type === 'sale') {
    $where_clauses[] = 'i.is_return = 0';
} elseif ($filter_type === 'return') {
    $where_clauses[] = 'i.is_return = 1';
}

// Filter by invoice number
if (!empty($filter_invoice_number)) {
    $where_clauses[] = 'i.invoice_number LIKE :invoice_number';
    $params[':invoice_number'] = '%' . $filter_invoice_number . '%';
}

// Filter by customer name (from either registered customers or cash customer name)
if (!empty($filter_customer_name)) {
    $where_clauses[] = '(c.name_ar LIKE :customer_name OR (i.customer_id IS NULL AND i.customer_name LIKE :customer_name))';
    $params[':customer_name'] = '%' . $filter_customer_name . '%';
}

$where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

$count_stmt = $db->prepare("SELECT COUNT(i.id) FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id $where_sql");
$count_stmt->execute($params);
$total_invoices = $count_stmt->fetchColumn();
$total_pages = ceil($total_invoices / $items_per_page);
$total_pages = max(1, $total_pages); // Ensure at least 1 page

// Adjust current page if it's out of bounds
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $items_per_page;

$invoices_query = "SELECT i.*, c.name_ar as registered_customer_name, c.phone as customer_phone, orig.invoice_number as original_invoice_number
                   FROM invoices i
                   LEFT JOIN customers c ON i.customer_id = c.id
                   LEFT JOIN invoices orig ON i.return_for_invoice_id = orig.id
                   $where_sql
                   ORDER BY i.created_at DESC
                   LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($invoices_query);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    // The key already includes the colon, e.g., ':invoice_number'
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
// --- END: DATA FETCHING ---

$page_title = 'إدارة الفواتير';
ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-lg mb-6 p-5" data-aos="fade-down">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-mai-brown rounded-lg flex items-center justify-center text-white text-2xl"><i
                    class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <h1 class="text-2xl font-bold text-mai-dark">إدارة الفواتير</h1>
                <p class="text-sm text-gray-500">عرض الفواتير العادية والمرتجعات وإنشاء مرتجعات جديدة.</p>
            </div>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert"
        data-aos="fade-left">
        <p class="font-bold">نجاح!</p>
        <p><?php echo $_SESSION['flash_success'];
        unset($_SESSION['flash_success']); ?></p>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert" data-aos="fade-left">
        <p class="font-bold">خطأ!</p>
        <p><?php echo $_SESSION['flash_error'];
        unset($_SESSION['flash_error']); ?></p>
    </div>
<?php endif; ?>

<!-- Invoices Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden" x-data="invoicesPage()" data-aos="fade-up">
    <!-- Filter Section -->
    <div class="p-4 bg-gray-50 border-b">
        <form method="GET" action="invoices.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="filter_invoice_number" class="text-sm font-bold text-gray-600">بحث بالرقم:</label>
                <input type="text" name="filter_invoice_number" id="filter_invoice_number"
                    class="mt-1 bg-white border border-gray-300 rounded-lg py-2 px-3 w-full text-sm focus:outline-none focus:ring-2 focus:ring-mai-brown"
                    placeholder="أدخل رقم الفاتورة..." value="<?= htmlspecialchars($filter_invoice_number) ?>">
            </div>
            <div>
                <label for="filter_customer_name" class="text-sm font-bold text-gray-600">بحث بالاسم:</label>
                <input type="text" name="filter_customer_name" id="filter_customer_name"
                    class="mt-1 bg-white border border-gray-300 rounded-lg py-2 px-3 w-full text-sm focus:outline-none focus:ring-2 focus:ring-mai-brown"
                    placeholder="أدخل اسم العميل..." value="<?= htmlspecialchars($filter_customer_name) ?>">
            </div>
            <div>
                <label for="filter_type" class="text-sm font-bold text-gray-600">فلتر حسب النوع:</label>
                <select name="filter_type" id="filter_type"
                    class="mt-1 bg-white border border-gray-300 rounded-lg py-2 px-3 w-full text-sm focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>عرض الكل</option>
                    <option value="sale" <?= $filter_type == 'sale' ? 'selected' : '' ?>>فواتير بيع</option>
                    <option value="return" <?= $filter_type == 'return' ? 'selected' : '' ?>>فواتير مرتجع</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit"
                    class="w-full bg-mai-brown text-white font-bold py-2 px-4 rounded-lg hover:bg-mai-dark focus:outline-none focus:ring-2 focus:ring-mai-brown focus:ring-opacity-50 transition-colors"><i
                        class="fas fa-search ml-2"></i>بحث</button>
                <a href="invoices.php"
                    class="w-full text-center bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50 transition-colors">إلغاء</a>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">رقم
                        الفاتورة</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">العميل
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">التاريخ
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">المبلغ
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">النوع
                    </th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">الإجراءات
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10 text-gray-500">
                            <i class="fas fa-receipt fa-3x mb-3"></i>
                            <p>لا توجد فواتير تطابق معايير البحث.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-800">
                                <?= htmlspecialchars($invoice['invoice_number']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars($invoice['registered_customer_name'] ?: ($invoice['customer_name'] ?: 'عميل نقدي')) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right" dir="ltr">
                                <?= date('Y-m-d h:i A', strtotime($invoice['created_at'])) ?>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-bold <?= $invoice['is_return'] ? 'text-red-600' : 'text-green-600' ?>">
                                <?= number_format($invoice['total_amount'], 2) ?> ر.س
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center"><?= getInvoiceTypeDisplay($invoice) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="toggleDetails(<?= $invoice['id'] ?>)"
                                        class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-blue-100"
                                        title="عرض"><i class="fas fa-eye"></i></button>
                                    <?php if (!$invoice['is_return']): ?>
                                        <?php if (hasPermission('invoices.edit')): ?>
                                            <button @click="openEditModal(<?= $invoice['id'] ?>)"
                                                class="text-purple-600 hover:text-purple-900 p-2 rounded-full hover:bg-purple-100"
                                                title="تعديل الفاتورة"><i class="fas fa-edit"></i></button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('invoices.create_return')): ?>
                                            <button @click="openReturnModal(<?= htmlspecialchars(json_encode($invoice)) ?>)"
                                                class="text-yellow-600 hover:text-yellow-900 p-2 rounded-full hover:bg-yellow-100"
                                                title="مرتجع"><i class="fas fa-undo"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button @click="printInvoice(<?= $invoice['id'] ?>)"
                                        class="text-green-600 hover:text-green-900 p-2 rounded-full hover:bg-green-100"
                                        title="طباعة"><i class="fas fa-print"></i></button>
                                    <button
                                        @click="sendInvoiceViaWhatsApp(<?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['customer_phone'] ?? '') ?>')"
                                        :disabled="isSendingWhatsAppFor === <?= $invoice['id'] ?>"
                                        class="text-green-600 hover:text-green-900 p-2 rounded-full hover:bg-green-100 disabled:text-gray-400 disabled:cursor-wait"
                                        title="إرسال عبر واتساب">
                                        <i class="fab fa-whatsapp" x-show="isSendingWhatsAppFor !== <?= $invoice['id'] ?>"></i>
                                        <i class="fas fa-spinner fa-spin"
                                            x-show="isSendingWhatsAppFor === <?= $invoice['id'] ?>"></i>
                                    </button>

                                    <?php if (hasPermission('invoices.delete')): ?>
                                        <button
                                            @click="confirmDelete(<?= $invoice['id'] ?>, '<?= htmlspecialchars(addslashes($invoice['invoice_number']), ENT_QUOTES) ?>')"
                                            class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-red-100" title="حذف"><i
                                                class="fas fa-trash"></i></button>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                        <!-- Expandable Details Row -->
                        <template x-if="expandedInvoiceId === <?= $invoice['id'] ?>">
                            <tr class="bg-gray-50">
                                <td colspan="6" class="p-4">
                                    <div x-show="!detailsCache[<?= $invoice['id'] ?>]" class="text-center py-4 text-gray-500"><i
                                            class="fas fa-spinner fa-spin mr-2"></i> جاري تحميل التفاصيل...</div>
                                    <template x-if="detailsCache[<?= $invoice['id'] ?>]">
                                        <div class="bg-white shadow-inner rounded-lg p-6 animate-fade-in">
                                            <!-- Invoice Header Info -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                                <div>
                                                    <h4 class="font-bold text-gray-800">فاتورة إلى:</h4>
                                                    <p
                                                        x-text="detailsCache[<?= $invoice['id'] ?>].invoice.registered_customer_name || detailsCache[<?= $invoice['id'] ?>].invoice.customer_name || 'عميل نقدي'">
                                                    </p>
                                                    <p x-text="detailsCache[<?= $invoice['id'] ?>].invoice.customer_phone || ''"
                                                        class="text-sm text-gray-600"></p>
                                                </div>
                                                <div class="text-left">
                                                    <h4 class="font-bold text-gray-800">فاتورة #<span
                                                            x-text="detailsCache[<?= $invoice['id'] ?>].invoice.invoice_number"></span>
                                                    </h4>
                                                    <p><strong>التاريخ:</strong> <span
                                                            x-text="new Date(detailsCache[<?= $invoice['id'] ?>].invoice.created_at).toLocaleString('ar-SA')"></span>
                                                    </p>
                                                    <p><strong>طريقة الدفع:</strong> <span
                                                            x-text="detailsCache[<?= $invoice['id'] ?>].invoice.payment_method || 'N/A'"></span>
                                                    </p>
                                                </div>
                                            </div>
                                            <!-- Invoice Items Table -->
                                            <table class="w-full text-sm mb-6">
                                                <thead class="bg-gray-100">
                                                    <tr>
                                                        <th class="py-2 px-3 text-right font-bold text-gray-600">الصنف</th>
                                                        <th class="py-2 px-3 text-right font-bold text-gray-600">الأخصائي</th>
                                                        <th class="py-2 px-3 text-center font-bold text-gray-600">الكمية</th>
                                                        <th class="py-2 px-3 text-center font-bold text-gray-600">سعر الوحدة
                                                        </th>
                                                        <th class="py-2 px-3 text-left font-bold text-gray-600">الإجمالي</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="item in detailsCache[<?= $invoice['id'] ?>].items"
                                                        :key="item.id">
                                                        <tr class="border-b">
                                                            <td class="py-3 px-3" x-text="item.item_name"></td>
                                                            <td class="py-3 px-3">
                                                                <!-- Specialist Edit Section -->
                                                                <div x-show="item.item_type === 'service'">
                                                                    <!-- Display View -->
                                                                    <div x-show="editingSpecialistItemId !== item.id"
                                                                        class="flex items-center justify-between">
                                                                        <span
                                                                            :class="{'text-gray-400 italic': !item.specialist_name}"
                                                                            x-text="item.specialist_name || 'لم يحدد'"></span>
                                                                        <?php if (hasPermission('invoices.edit')): ?>
                                                                            <button @click="editSpecialist(item)"
                                                                                class="text-xs text-blue-600 hover:underline p-1">تعديل</button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <!-- Edit View -->
                                                                    <div x-show="editingSpecialistItemId === item.id" x-cloak
                                                                        class="flex items-center gap-2">
                                                                        <select x-model="item.specialist_id"
                                                                            class="block w-full border-gray-300 rounded-md shadow-sm text-xs focus:ring-mai-brown focus:border-mai-brown">
                                                                            <option value="">-- إلغاء التعيين --</option>
                                                                            <template x-for="specialist in allSpecialists"
                                                                                :key="specialist.id">
                                                                                <option :value="specialist.id"
                                                                                    x-text="specialist.name_ar"></option>
                                                                            </template>
                                                                        </select>
                                                                        <button @click="saveSpecialist(item)"
                                                                            class="p-1 text-green-600 hover:text-green-800"><i
                                                                                class="fas fa-check"></i></button>
                                                                        <button @click="cancelEditSpecialist(item)"
                                                                            class="p-1 text-red-600 hover:text-red-800"><i
                                                                                class="fas fa-times"></i></button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="py-3 px-3 text-center" x-text="item.quantity"></td>
                                                            <td class="py-3 px-3 text-center"
                                                                x-text="Number(item.unit_price).toFixed(2)"></td>
                                                            <td class="py-3 px-3 text-left font-semibold"
                                                                x-text="Number(item.total_price).toFixed(2)"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                            <!-- Invoice Totals -->
                                            <div class="grid grid-cols-2 gap-4">
                                                <div></div> <!-- Spacer -->
                                                <div class="space-y-2 text-sm">
                                                    <div class="flex justify-between"><span class="text-gray-600">المجموع
                                                            الفرعي:</span> <strong
                                                            x-text="Number(detailsCache[<?= $invoice['id'] ?>].invoice.subtotal || 0).toFixed(2) + ' ر.س'"></strong>
                                                    </div>
                                                    <div class="flex justify-between"><span class="text-gray-600">الخصم:</span>
                                                        <strong class="text-red-600"
                                                            x-text="'-' + Number(detailsCache[<?= $invoice['id'] ?>].invoice.discount_amount || 0).toFixed(2) + ' ر.س'"></strong>
                                                    </div>
                                                    <div class="flex justify-between"><span class="text-gray-600">الضريبة (<span
                                                                x-text="detailsCache[<?= $invoice['id'] ?>].invoice.tax_rate || 15"></span>%):</span>
                                                        <strong
                                                            x-text="Number(detailsCache[<?= $invoice['id'] ?>].invoice.tax_amount || 0).toFixed(2) + ' ر.س'"></strong>
                                                    </div>
                                                    <div class="flex justify-between border-t pt-2 mt-2 text-lg"><span
                                                            class="font-bold">المجموع الإجمالي:</span> <strong
                                                            class="font-black text-mai-brown"
                                                            x-text="Number(detailsCache[<?= $invoice['id'] ?>].invoice.total_amount).toFixed(2) + ' ر.س'"></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- START: MODERN PAGINATION -->
    <?php if ($total_pages > 1):
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $baseUrl = $_SERVER['PHP_SELF'] . '?' . ($queryString ? $queryString . '&' : '');
        $pageLink = fn($p) => $baseUrl . 'page=' . $p;
        $window = 1; // Number of page links to show around the current page
        ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 rounded-b-xl">
            <!-- Mobile Pagination -->
            <div class="flex-1 flex justify-between sm:hidden">
                <a href="<?= ($page > 1) ? $pageLink($page - 1) : '#' ?>"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= ($page <= 1) ? 'opacity-60 cursor-not-allowed' : '' ?>">السابق</a>
                <a href="<?= ($page < $total_pages) ? $pageLink($page + 1) : '#' ?>"
                    class="mr-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= ($page >= $total_pages) ? 'opacity-60 cursor-not-allowed' : '' ?>">التالي</a>
            </div>

            <!-- Desktop Pagination -->
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        عرض
                        <span class="font-medium"><?= $offset + 1 ?></span>
                        إلى
                        <span class="font-medium"><?= min($offset + $items_per_page, $total_invoices) ?></span>
                        من
                        <span class="font-medium"><?= $total_invoices ?></span>
                        نتائج
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <a href="<?= ($page > 1) ? $pageLink($page - 1) : '#' ?>"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= ($page <= 1) ? 'opacity-60 cursor-not-allowed' : '' ?>">
                            <span class="sr-only">السابق</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)): ?>
                                <a href="<?= $pageLink($i) ?>" aria-current="page"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= ($i == $page) ? 'z-10 bg-mai-cream border-mai-brown text-mai-dark' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>"><?= $i ?></a>
                            <?php elseif ($i == $page - ($window + 1) || $i == $page + ($window + 1)): ?>
                                <span
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                            <?php endif;
                        endfor;
                        ?>
                        <a href="<?= ($page < $total_pages) ? $pageLink($page + 1) : '#' ?>"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= ($page >= $total_pages) ? 'opacity-60 cursor-not-allowed' : '' ?>">
                            <span class="sr-only">التالي</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- END: MODERN PAGINATION -->

    <!-- Hidden Delete Form -->
    <form method="POST" action="invoices.php<?= '?' . http_build_query($_GET) ?>" id="deleteForm" class="hidden">
        <input type="hidden" name="action" value="delete_invoice">
        <input type="hidden" name="invoice_id" id="invoiceIdToDelete">
    </form>

    <!-- Return Modal -->
    <div x-show="isReturnModalOpen" x-cloak
        class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div @click.away="isReturnModalOpen = false"
            class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] flex flex-col" x-show="isReturnModalOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-mai-dark">إنشاء مرتجع للفاتورة #<span
                        x-text="invoice.invoice_number"></span></h3><button type="button"
                    @click="isReturnModalOpen = false" class="text-gray-400 hover:text-red-500 text-2xl"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="border-b border-gray-200 px-6">
                <nav class="-mb-px flex space-x-6" dir="rtl"><button @click="returnTab = 'items'"
                        :class="{'border-mai-brown text-mai-brown': returnTab === 'items', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': returnTab !== 'items'}"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"><i
                            class="fas fa-boxes ml-2"></i>إرجاع أصناف</button><button @click="returnTab = 'value'"
                        :class="{'border-mai-brown text-mai-brown': returnTab === 'value', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': returnTab !== 'value'}"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"><i
                            class="fas fa-money-bill-wave ml-2"></i>إرجاع مبلغ</button></nav>
            </div>

            <!-- RETURN ITEMS TAB -->
            <div x-show="returnTab === 'items'" class="flex flex-col flex-grow">
                <form method="POST" action="invoices.php" class="flex flex-col flex-grow"><input type="hidden"
                        name="action" value="create_item_return"><input type="hidden" name="original_invoice_id"
                        :value="invoice.id">
                    <div class="p-6 overflow-y-auto flex-grow">
                        <p class="mb-5 text-sm text-gray-600">حدد الأصناف والكميات التي ترغب في إرجاعها. سيتم إعادة
                            المنتجات إلى المخزون.</p>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-3 text-right font-bold text-gray-600">الصنف</th>
                                    <th class="p-3 text-center font-bold text-gray-600">الكمية المباعة</th>
                                    <th class="p-3 text-center font-bold text-gray-600" style="width: 120px;">الكمية
                                        المرتجعة</th>
                                </tr>
                            </thead>
                            <tbody><template x-for="item in items" :key="item.id">
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-medium" x-text="item.item_name"></td>
                                        <td class="p-3 text-center font-semibold text-gray-700" x-text="item.quantity">
                                        </td>
                                        <td class="p-3 text-center"><input type="number"
                                                :name="'return_items[' + item.id + ']'"
                                                class="w-24 text-center border-gray-300 rounded-md p-2 focus:ring-mai-brown focus:border-mai-brown"
                                                value="0" min="0" :max="item.quantity"></td>
                                    </tr>
                                </template></tbody>
                        </table>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                            <div><label for="refund_from_account_id"
                                    class="block text-sm font-medium text-gray-700">إعادة المبلغ من حساب*</label>
                                <select name="refund_from_account_id" id="refund_from_account_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm">
                                    <option value="">-- اختر حساب الدفع --</option>
                                    <?php foreach ($payment_accounts as $p_account): ?>
                                        <option value="<?= $p_account['id'] ?>">
                                            <?= htmlspecialchars($p_account['account_name_ar']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label for="return_reason" class="block text-sm font-medium text-gray-700">سبب الإرجاع
                                    (اختياري)</label><textarea name="return_reason" id="return_reason" rows="1"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="p-5 bg-gray-50 border-t flex justify-end gap-3"><button type="button"
                            @click="isReturnModalOpen = false"
                            class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 font-semibold">إلغاء</button><button
                            type="submit"
                            class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-bold">تأكيد مرتجع
                            الأصناف</button></div>
                </form>
            </div>

            <!-- RETURN VALUE TAB -->
            <div x-show="returnTab === 'value'" class="flex flex-col flex-grow">
                <form method="POST" action="invoices.php" class="flex flex-col flex-grow"><input type="hidden"
                        name="action" value="create_value_return"><input type="hidden" name="original_invoice_id"
                        :value="invoice.id">
                    <div class="p-6 overflow-y-auto flex-grow">
                        <p class="mb-5 text-sm text-gray-600">أدخل المبلغ الذي ترغب في إرجاعه للعميل. هذه العملية مالية
                            فقط ولن تؤثر على مخزون المنتجات.</p>
                        <div class="bg-blue-50 border-blue-200 border-l-4 p-4 rounded-md">
                            <p class="font-bold text-blue-800">إجمالي الفاتورة الأصلية: <span
                                    x-text="Number(invoice.total_amount).toFixed(2)"></span> ر.س</p>
                        </div>
                        <div class="mt-5"><label for="return_amount"
                                class="block text-sm font-bold text-gray-800">المبلغ المراد إرجاعه (ر.س)</label><input
                                type="number" name="return_amount" id="return_amount" step="0.01" min="0.01"
                                :max="invoice.total_amount"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-lg p-3 text-center font-bold"
                                placeholder="e.g., 200.00" required></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                            <div><label for="refund_from_account_id_value"
                                    class="block text-sm font-medium text-gray-700">إعادة المبلغ من حساب*</label>
                                <select name="refund_from_account_id_value" id="refund_from_account_id_value" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm">
                                    <option value="">-- اختر حساب الدفع --</option>
                                    <?php foreach ($payment_accounts as $p_account): ?>
                                        <option value="<?= $p_account['id'] ?>">
                                            <?= htmlspecialchars($p_account['account_name_ar']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label for="return_reason_value" class="block text-sm font-medium text-gray-700">سبب
                                    الإرجاع (اختياري)</label><textarea name="return_reason_value"
                                    id="return_reason_value" rows="1"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="p-5 bg-gray-50 border-t flex justify-end gap-3"><button type="button"
                            @click="isReturnModalOpen = false"
                            class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 font-semibold">إلغاء</button><button
                            type="submit"
                            class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-bold">تأكيد مرتجع
                            المبلغ</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- START: Edit Invoice Modal -->
    <div x-show="isEditModalOpen" x-cloak
        class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div @click.away="isEditModalOpen = false"
            class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col" x-show="isEditModalOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-mai-dark">تعديل الفاتورة #<span
                        x-text="editingInvoice.invoice.invoice_number"></span></h3>
                <button type="button" @click="isEditModalOpen = false"
                    class="text-gray-400 hover:text-red-500 text-2xl"><i class="fas fa-times"></i></button>
            </div>

            <div x-show="editModalLoading" class="text-center p-10"><i
                    class="fas fa-spinner fa-spin fa-3x text-mai-brown"></i></div>

            <form method="POST" action="invoices.php" class="flex flex-col flex-grow" x-show="!editModalLoading">
                <input type="hidden" name="action" value="update_invoice">
                <input type="hidden" name="invoice_id" :value="editingInvoice.invoice.id">

                <div class="p-6 overflow-y-auto flex-grow space-y-6">
                    <!-- Customer and Payment Info -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">العميل</label>
                            <select name="customer_id" id="customer_id" x-model="editingInvoice.invoice.customer_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm">
                                <option value="">-- عميل نقدي --</option>
                                <?php foreach ($all_customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">طريقة
                                الدفع</label>
                            <select name="payment_method" id="payment_method"
                                x-model="editingInvoice.invoice.payment_method"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm">
                                <option value="cash">نقدي</option>
                                <option value="card">بطاقة</option>
                                <option value="mixed">مختلط</option>
                            </select>
                        </div>
                        <div>
                            <label for="invoice_date" class="block text-sm font-medium text-gray-700">تاريخ
                                الفاتورة</label>
                            <input type="datetime-local" name="invoice_date" id="invoice_date"
                                x-model="editingInvoice.invoice.created_at"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-mai-brown focus:ring-mai-brown sm:text-sm"
                                required>
                        </div>
                    </div>
                    <div x-show="editingInvoice.invoice.payment_method === 'mixed'"
                        class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg">
                        <div>
                            <label class="text-sm font-medium text-gray-700">المبلغ النقدي</label>
                            <input type="number" name="cash_amount" x-model.number="editingInvoice.invoice.cash_amount"
                                @input="calculateTotals()" step="0.01"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">مبلغ البطاقة</label>
                            <input type="number" name="card_amount" x-model.number="editingInvoice.invoice.card_amount"
                                @input="calculateTotals()" step="0.01"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>

                    <!-- Invoice Items Table -->
                    <div class="overflow-x-auto border rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-3 text-right">الصنف</th>
                                    <th class="p-3 text-right">الأخصائي</th>
                                    <th class="p-3 text-center w-24">الكمية</th>
                                    <th class="p-3 text-center w-32">السعر</th>
                                    <th class="p-3 text-left w-32">الإجمالي</th>
                                    <th class="p-3 text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, index) in editingInvoice.items" :key="index">
                                    <tr class="border-b">
                                        <td class="p-2"><input type="text" :name="'items['+index+'][item_name]'"
                                                x-model="item.item_name"
                                                class="w-full rounded-md border-gray-300 text-sm" required></td>
                                        <td class="p-2">
                                            <select :name="'items['+index+'][specialist_id]'"
                                                x-model="item.specialist_id"
                                                class="w-full rounded-md border-gray-300 text-sm">
                                                <option value="">-- اختر --</option>
                                                <template x-for="specialist in allSpecialists" :key="specialist.id">
                                                    <option :value="specialist.id" x-text="specialist.name_ar"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="p-2"><input type="number" :name="'items['+index+'][quantity]'"
                                                x-model.number="item.quantity" @input="calculateTotals()" min="1"
                                                class="w-full text-center rounded-md border-gray-300 text-sm" required>
                                        </td>
                                        <td class="p-2"><input type="number" :name="'items['+index+'][unit_price]'"
                                                x-model.number="item.unit_price" @input="calculateTotals()" step="0.01"
                                                min="0" class="w-full text-center rounded-md border-gray-300 text-sm"
                                                required></td>
                                        <td class="p-2 text-left font-semibold"
                                            x-text="(item.quantity * item.unit_price).toFixed(2)"></td>
                                        <td class="p-2 text-center">
                                            <button type="button" @click="removeItemRow(index)"
                                                class="text-red-500 hover:text-red-700"><i
                                                    class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" @click="addNewItemRow()"
                        class="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"><i
                            class="fas fa-plus mr-2"></i>إضافة صنف جديد</button>

                    <!-- Totals Section -->
                    <div class="grid grid-cols-2 gap-6 pt-4 border-t">
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">ملاحظات</label>
                            <textarea name="notes" id="notes" rows="3" x-model="editingInvoice.invoice.notes"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-gray-600">المجموع الفرعي:</span> <strong
                                    x-text="Number(editingInvoice.invoice.subtotal).toFixed(2) + ' ر.س'"></strong></div>
                            <div class="flex justify-between"><span class="text-gray-600">الضريبة (<span
                                        x-text="editingInvoice.invoice.tax_rate || 15"></span>%):</span> <strong
                                    x-text="Number(editingInvoice.invoice.tax_amount).toFixed(2) + ' ر.س'"></strong>
                            </div>
                            <div class="flex justify-between border-t pt-2 mt-2 text-lg"><span
                                    class="font-bold">الإجمالي:</span> <strong class="font-black text-mai-brown"
                                    x-text="Number(editingInvoice.invoice.total_amount).toFixed(2) + ' ر.س'"></strong>
                            </div>
                            <div x-show="paymentMismatch" x-cloak
                                class="text-red-600 text-xs font-bold text-center p-2 bg-red-50 rounded-md">
                                مجموع المدفوعات لا يطابق الإجمالي!
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-5 bg-gray-50 border-t flex justify-end gap-3">
                    <button type="button" @click="isEditModalOpen = false"
                        class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 font-semibold">إلغاء</button>
                    <button type="submit" :disabled="paymentMismatch"
                        class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 font-bold disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <i class="fas fa-save mr-2"></i>حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- END: Edit Invoice Modal -->

</div>

<!-- *** START: ALPINEJS LOGIC *** -->
<<!-- *** START: ALPINEJS LOGIC *** -->
    <script>
        function invoicesPage() {
            return {
                isReturnModalOpen: false,
                isEditModalOpen: false,
                editModalLoading: true,
                invoice: {},
                items: [],
                editingInvoice: { invoice: {}, items: [] },
                returnTab: 'items',
                expandedInvoiceId: null,
                detailsCache: {},
                printSettings: <?php echo $print_settings_json; ?>,
                allSpecialists: <?php echo $all_specialists_json; ?>,
                allCustomers: <?php echo $all_customers_json; ?>,
                editingSpecialistItemId: null,
                originalSpecialistId: null,
                isSendingWhatsAppFor: null, // To track which invoice is being sent
                whatsAppStatusText: '',   // Optional: for more detailed status

                get paymentMismatch() {
                    if (this.editingInvoice.invoice.payment_method !== 'mixed') return false;
                    const totalPaid = (parseFloat(this.editingInvoice.invoice.cash_amount) || 0) + (parseFloat(this.editingInvoice.invoice.card_amount) || 0);
                    return totalPaid.toFixed(2) !== parseFloat(this.editingInvoice.invoice.total_amount).toFixed(2);
                },

                toggleDetails(invoiceId) {
                    if (this.expandedInvoiceId === invoiceId) {
                        this.expandedInvoiceId = null;
                        return;
                    }
                    this.editingSpecialistItemId = null; // Reset edit mode when switching invoices
                    this.fetchInvoiceDetails(invoiceId);
                    this.expandedInvoiceId = invoiceId;
                },

                editSpecialist(item) {
                    this.originalSpecialistId = item.specialist_id;
                    this.editingSpecialistItemId = item.id;
                },

                cancelEditSpecialist(item) {
                    item.specialist_id = this.originalSpecialistId;
                    this.editingSpecialistItemId = null;
                    this.originalSpecialistId = null;
                },

                async saveSpecialist(item) {
                    const formData = new FormData();
                    formData.append('action', 'update_specialist');
                    formData.append('invoice_item_id', item.id);
                    formData.append('specialist_id', item.specialist_id);

                    try {
                        const response = await fetch('invoices.php', { method: 'POST', body: formData });
                        const result = await response.json();

                        if (result.success) {
                            item.specialist_name = result.new_specialist_name;
                            this.editingSpecialistItemId = null;
                            this.originalSpecialistId = null;
                        } else {
                            alert('فشل التحديث: ' + result.message);
                            this.cancelEditSpecialist(item);
                        }
                    } catch (error) {
                        alert('حدث خطأ في الشبكة.');
                        this.cancelEditSpecialist(item);
                    }
                },

                openReturnModal(invoiceData) {
                    this.invoice = invoiceData;
                    this.returnTab = 'items';
                    this.fetchInvoiceDetails(invoiceData.id, () => {
                        this.items = this.detailsCache[invoiceData.id].items;
                        this.isReturnModalOpen = true;
                    });
                },

                async openEditModal(invoiceId) {
                    this.isEditModalOpen = true;
                    this.editModalLoading = true;
                    this.editingInvoice = { invoice: {}, items: [] };

                    await this.fetchInvoiceDetails(invoiceId, () => {
                        // Deep copy to avoid modifying the cache directly
                        this.editingInvoice.invoice = JSON.parse(JSON.stringify(this.detailsCache[invoiceId].invoice));
                        this.editingInvoice.items = JSON.parse(JSON.stringify(this.detailsCache[invoiceId].items));

                        // Format the date for the datetime-local input field
                        if (this.editingInvoice.invoice.created_at) {
                            this.editingInvoice.invoice.created_at = this.editingInvoice.invoice.created_at.slice(0, 16).replace(' ', 'T');
                        }

                        this.editModalLoading = false;
                    });
                },

                addNewItemRow() {
                    this.editingInvoice.items.push({
                        item_name: '',
                        quantity: 1,
                        unit_price: 0,
                        specialist_id: ''
                    });
                },

                removeItemRow(index) {
                    this.editingInvoice.items.splice(index, 1);
                    this.calculateTotals();
                },

                calculateTotals() {
                    let subtotal = 0;
                    this.editingInvoice.items.forEach(item => {
                        subtotal += (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
                    });

                    const taxRate = parseFloat(this.editingInvoice.invoice.tax_rate) || 0;
                    const taxAmount = subtotal * (taxRate / 100);
                    const totalAmount = subtotal + taxAmount;

                    this.editingInvoice.invoice.subtotal = subtotal.toFixed(2);
                    this.editingInvoice.invoice.tax_amount = taxAmount.toFixed(2);
                    this.editingInvoice.invoice.total_amount = totalAmount.toFixed(2);
                },

                confirmDelete(invoiceId, invoiceNumber) {
                    if (confirm(`هل أنت متأكد؟ سيتم حذف الفاتورة #${invoiceNumber} والقيد المحاسبي المرتبط بها نهائياً.`)) {
                        document.getElementById('invoiceIdToDelete').value = invoiceId;
                        document.getElementById('deleteForm').submit();
                    }
                },

                fetchInvoiceDetails(invoiceId, callback = null) {
                    return new Promise((resolve, reject) => {
                        if (this.detailsCache[invoiceId]) {
                            if (callback) callback();
                            resolve();
                            return;
                        }
                        this.detailsCache[invoiceId] = null; // Loading state
                        fetch(`invoices.php?action=get_details&id=${invoiceId}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Network response was not ok');
                                return res.json();
                            })
                            .then(result => {
                                if (result.success) {
                                    this.detailsCache[invoiceId] = result.data;
                                    if (callback) callback();
                                    resolve();
                                } else {
                                    alert('فشل في تحميل تفاصيل الفاتورة: ' + result.message);
                                    delete this.detailsCache[invoiceId];
                                    reject(new Error(result.message));
                                }
                            }).catch((error) => {
                                alert('حدث خطأ في الشبكة.');
                                delete this.detailsCache[invoiceId];
                                reject(error);
                            });
                    });
                },

                async printInvoice(invoiceId) {
                    if (!this.detailsCache[invoiceId]) {
                        await this.fetchInvoiceDetails(invoiceId);
                    }

                    const invoiceData = this.detailsCache[invoiceId].invoice;
                    const itemsData = this.detailsCache[invoiceId].items;

                    let zatcaQR = '';
                    if (parseFloat(invoiceData.tax_amount) > 0) {
                        try {
                            const formData = new FormData();
                            formData.append('action', 'generate_zatca_qr');
                            formData.append('invoice_id', invoiceId);
                            const qrResponse = await fetch('pos_actions.php', { method: 'POST', body: formData });
                            const qrResult = await qrResponse.json();
                            if (qrResult.success) {
                                zatcaQR = qrResult.qr_code_uri;
                            }
                        } catch (error) {
                            console.error('QR Regeneration failed:', error);
                        }
                    }

                    this.printReceipt(invoiceData, itemsData, zatcaQR);
                },

                printReceipt(invoiceData, itemsData, zatcaQR) {
                    let itemsHtml = '';
                    itemsData.forEach(item => {
                        let specialistHtml = '';
                        if (item.item_type === 'service' && item.specialist_name) {
                            specialistHtml = `<div class="specialist-info">الأخصائي: ${item.specialist_name}</div>`;
                        }
                        itemsHtml += `<tr class="item-row"><td class="item-name">${item.item_name}${specialistHtml}</td><td class="item-qty">${item.quantity}</td><td class="item-price">${Number(item.unit_price).toFixed(2)}</td><td class="item-total">${Number(item.total_price).toFixed(2)}</td></tr>`;
                    });

                    const paymentMethodMap = { 'cash': 'نقدي', 'card': 'بطاقة', 'bank_transfer': 'تحويل بنكي', 'mixed': 'مختلط' };
                    let paymentDetailsHtml = `<div class="detail-row"><span>طريقة الدفع:</span><span>${paymentMethodMap[invoiceData.payment_method] || 'غير محدد'}</span></div>`;
                    if (invoiceData.payment_method === 'mixed') {
                        paymentDetailsHtml += `<div class="detail-row sub-detail"><span>- نقداً:</span><span>${Number(invoiceData.cash_amount).toFixed(2)} ر.س</span></div><div class="detail-row sub-detail"><span>- بالبطاقة:</span><span>${Number(invoiceData.card_amount).toFixed(2)} ر.س</span></div>`
                    }

                    let taxRowHtml = '';
                    if (invoiceData.tax_amount && parseFloat(invoiceData.tax_amount) > 0) {
                        taxRowHtml = `<tr class="summary-row"><td>ضريبة القيمة المضافة (${Number(invoiceData.tax_rate).toFixed(0)}%)</td><td>${Number(invoiceData.tax_amount).toFixed(2)} ر.س</td></tr>`
                    }

                    const invoiceDate = new Date(invoiceData.created_at).toLocaleString('ar', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true });
                    const customerDisplayName = invoiceData.registered_customer_name || invoiceData.customer_name || 'عميل نقدي';

                    const receiptHtml = `
                <div class="receipt-container">
                    <div class="header">
                        <h2>${this.printSettings.salonName}</h2>
                        <p>فاتورة ضريبية مبسطة</p>
                        ${this.printSettings.isTaxEnabled && this.printSettings.taxNumber ? `<p>الرقم الضريبي: ${this.printSettings.taxNumber}</p>` : ''}
                         <p style="font-size: 10px;">المملكة العربية السعودية، الطائف، حي الأخباب</p>
                    </div>
                    <div class="invoice-details">
                        <div class="detail-row"><span>رقم الفاتورة:</span><span>${invoiceData.invoice_number}</span></div>
                        <div class="detail-row"><span>التاريخ:</span><span>${invoiceDate}</span></div>
                        <div class="detail-row"><span>العميل:</span><span>${customerDisplayName}</span></div>
                    </div>
                    <table class="items-table"><thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>المجموع</th></tr></thead><tbody>${itemsHtml}</tbody></table>
                    <table class="summary-table"><tbody><tr class="summary-row"><td>المجموع الفرعي</td><td>${Number(invoiceData.subtotal).toFixed(2)} ر.س</td></tr>${taxRowHtml}<tr class="summary-row"><td>الخصم</td><td>${Number(invoiceData.discount_amount).toFixed(2)} ر.س</td></tr><tr class="summary-row total"><td>الإجمالي</td><td>${Number(invoiceData.total_amount).toFixed(2)} ر.س</td></tr></tbody></table>
                    <div class="payment-details">${paymentDetailsHtml}</div>
                    ${zatcaQR ? `<div class="qr-code"><img src="${zatcaQR}" alt="QR Code"></div>` : ''}
                    <div class="footer"><p>${this.printSettings.footerText}</p><p>ونتمنى زيارتكم قريبا</p></div>
                </div>`;

                    const printStyles = `<style> @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap'); body{margin:0;padding:0;background-color:#f4f4f4;font-family:'Tajawal',sans-serif}.receipt-container{width:300px;margin:0 auto;background:#fff;padding:15px;color:#333;direction:rtl}.header{text-align:center;border-bottom:2px dashed #ccc;padding-bottom:10px;margin-bottom:10px}.header h2{margin:0;font-size:20px;color:#111}.header p{margin:2px 0;font-size:11px}.invoice-details,.payment-details{font-size:12px;margin-bottom:15px}.detail-row{display:flex;justify-content:space-between;padding:2px 0}.detail-row.sub-detail{padding-right:15px;font-size:11px;color:#555}.detail-row span:first-child{font-weight:bold}.items-table{width:100%;border-collapse:collapse;margin-bottom:15px}.items-table thead{border-bottom:1px solid #555}.items-table th{padding:5px;text-align:right;font-size:11px;font-weight:bold}.items-table th:nth-child(2),.items-table th:nth-child(3),.items-table th:nth-child(4){text-align:center}.item-row td{padding:6px 5px;font-size:12px;vertical-align:top}.item-name{text-align:right}.specialist-info{font-size:10px;color:#555;padding-top:4px}.item-qty,.item-price,.item-total{text-align:center}.summary-table{width:100%;margin-top:15px;border-top:2px dashed #ccc;padding-top:10px}.summary-row{font-size:12px}.summary-row td{padding:4px}.summary-row td:last-child{text-align:left;font-weight:bold}.summary-row.total{font-size:16px;font-weight:700;color:#000}.summary-row.total td{padding-top:8px}.payment-details{border-top:2px dashed #ccc;padding-top:10px}.qr-code{text-align:center;padding:15px 0}.qr-code img{width:120px;height:120px;display:block;margin:0 auto}.footer{text-align:center;border-top:2px dashed #ccc;padding-top:10px;font-size:11px}.footer p{margin:2px 0}</style>`;
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write('<html><head><title>طباعة الفاتورة</title>' + printStyles + '</head><body>');
                    printWindow.document.write(receiptHtml);
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(() => { printWindow.print() }, 500);
                },

                async getInvoicePdfLink(invoiceId) {
                    try {
                        this.whatsAppStatusText = 'جاري إنشاء PDF...';
                        const formData = new FormData();
                        formData.append('action', 'generate_invoice_pdf');
                        formData.append('invoice_id', invoiceId);
                        const response = await fetch('pos_actions.php', { method: 'POST', body: formData });
                        const result = await response.json();

                        if (result.success && result.pdf_url) {
                            return result.pdf_url;
                        } else {
                            console.error('Server PDF Error:', result.message);
                            alert('حدث خطأ أثناء إنشاء ملف الفاتورة: ' + (result.message || 'Unknown server error'));
                            return null;
                        }
                    } catch (error) {
                        console.error('Failed to fetch PDF link:', error);
                        alert('حدث خطأ في الشبكة.');
                        return null;
                    }
                },

                async sendInvoiceViaWhatsApp(invoiceId, customerPhone) {
                    // Prevent multiple clicks for the SAME invoice
                    if (this.isSendingWhatsAppFor === invoiceId) return;

                    if (!customerPhone || customerPhone.trim() === '') {
                        alert('لا يوجد رقم جوال مسجل لهذا العميل.');
                        return;
                    }

                    // --- SOLUTION: Open a blank window immediately ---
                    const whatsappWindow = window.open('', '_blank');
                    if (whatsappWindow) {
                        // You can write a temporary message
                        whatsappWindow.document.write('<p>الرجاء الانتظار، جاري تجهيز رسالتك...</p>');
                    } else {
                        // This will trigger if the browser blocks the initial pop-up
                        alert('تم حظر النافذة المنبثقة. الرجاء السماح بالنوافذ المنبثقة لهذا الموقع لإرسال رسائل واتساب.');
                        return; // Stop execution if the window couldn't be opened
                    }

                    this.isSendingWhatsAppFor = invoiceId;
                    try {
                        // Make the asynchronous call to get the PDF link
                        const pdfLink = await this.getInvoicePdfLink(invoiceId);

                        if (pdfLink) {
                            this.whatsAppStatusText = 'جاري فتح واتساب...';
                            let phone = customerPhone.trim().replace(/\D/g, '');
                            if (phone.startsWith('05')) {
                                phone = '966' + phone.substring(1);
                            } else if (phone.startsWith('5') && phone.length === 9) {
                                phone = '966' + phone;
                            }

                            // Ensure invoice details are loaded to get the invoice number
                            if (!this.detailsCache[invoiceId]) {
                                await this.fetchInvoiceDetails(invoiceId);
                            }
                            const invoiceNumber = this.detailsCache[invoiceId]?.invoice?.invoice_number || invoiceId;

                            const message = encodeURIComponent(
                                `مرحباً! شكراً لزيارتكم لصالون مي.\n` +
                                `رقم فاتورتكم هو ${invoiceNumber}.\n\n` +
                                `لتحميل الفاتورة اضغط على الرابط:\n` +
                                `${pdfLink}\n\n` +
                                `Hello! Thank you for visiting Mai Salon.\n` +
                                `Your invoice number is ${invoiceNumber}.\n\n` +
                                `To download the invoice, click the link:\n` +
                                `${pdfLink}`
                            );
                            const whatsappUrl = `https://wa.me/${phone}?text=${message}`;

                            // --- SOLUTION: Redirect the window you already opened ---
                            if (whatsappWindow && !whatsappWindow.closed) {
                                whatsappWindow.location.href = whatsappUrl;
                            } else {
                                // Fallback in case the user closed the window before the link was ready
                                window.open(whatsappUrl, '_blank');
                            }

                        } else {
                            // --- SOLUTION: If PDF generation fails, close the blank window ---
                            if (whatsappWindow) {
                                whatsappWindow.close();
                            }
                            alert('فشل في إنشاء رابط الفاتورة. يرجى المحاولة مرة أخرى.');
                        }
                    } catch (error) {
                        console.error('An error occurred during sendInvoiceViaWhatsApp:', error);
                        // Also close the blank window if any other error occurs
                        if (whatsappWindow) {
                            whatsappWindow.close();
                        }
                        alert('حدث خطأ فني أثناء إعداد رسالة الواتساب.');
                    }
                    finally {
                        this.isSendingWhatsAppFor = null;
                        this.whatsAppStatusText = '';
                    }
                },
            }
        }
    </script>
    <!-- *** END: ALPINEJS LOGIC *** -->

    <?php
    $page_content = ob_get_clean();
    $page_specific_head = '<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"><script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script><style>.animate-fade-in { animation: fadeIn 0.5s ease-in-out; } @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } [x-cloak] { display: none !important; }</style>';
    $page_specific_footer_scripts = '<script>AOS.init({ duration: 600, once: true, offset: 20 });</script>';
    include 'layouts/responsive_base.php';
    ?>
    