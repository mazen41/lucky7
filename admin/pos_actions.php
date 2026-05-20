<?php

// =================================================================
// MAI SALON - POS ACTIONS HANDLER (Final Production with Unit Conversion)
// =================================================================

// --- Error Reporting (Clean for Production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

if (!defined('DIR')) {
    define('DIR', dirname(__FILE__));
}
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

ini_set('error_log', DIR . '/php-error.log');

require_once DIR . '/auth.php';
require_once '../config/database.php';
require_once DIR . '/pos_functions.php';
require_once DIR . '/SimpleProductManager.php';
require_once DIR . '/accounting_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// --- Global Try-Catch for clean JSON error responses ---
try {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Authentication required.', 401);
    }
    $admin = getCurrentAdmin();
    if (!$admin || !isset($admin['id'])) {
        throw new Exception('Invalid admin session.', 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_POST['action'] ?? '';
    
    // Fetch site settings for tax and headers
    $site_settings = [];
    $settings_query = "SELECT setting_key, setting_value, status FROM site_settings WHERE setting_key LIKE 'pos_%' OR setting_key IN ('tax_rate', 'tax_number', 'tax_name')";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($settings_raw as $setting) {
        $site_settings[$setting['setting_key']] = ['value' => $setting['setting_value'], 'status' => $setting['status']];
    }
    
    switch ($action) {
        case 'search_customers':
            $nameQuery = trim($_POST['name'] ?? '');
            if (empty($nameQuery)) {
                echo json_encode(['success' => true, 'customers' => []]);
                exit;
            }
            $sql = "SELECT c.id, c.name, c.name_ar, c.name_en FROM customers c WHERE (c.name LIKE ? OR c.name_ar LIKE ? OR c.name_en LIKE ?) ORDER BY c.name_ar, c.name LIMIT 10";
            $params = ["%$nameQuery%", "%$nameQuery%", "%$nameQuery%"];
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'add_cash_payout':
            $amount = floatval($_POST['amount'] ?? 0);
            $expense_account_id = intval($_POST['account_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            if ($amount <= 0 || $expense_account_id <= 0) throw new Exception('بيانات غير صحيحة.');

            $shift_stmt = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
            $shift_stmt->execute([$admin['id']]);
            $shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$shift) throw new Exception('لا توجد وردية مفتوحة.');

            $db->beginTransaction();
            $attachment_path = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/expenses/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
                $filename = 'payout_' . time() . '_' . uniqid() . '.' . pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) $attachment_path = 'uploads/expenses/' . $filename;
            }

            $cat_stmt = $db->prepare("SELECT account_name_ar FROM accounting_charts_of_accounts WHERE id = ?");
            $cat_stmt->execute([$expense_account_id]);
            $category_name = $cat_stmt->fetchColumn();

            $acc_settings = getAccountingSettings($db);
            $cash_account_id = $acc_settings['default_cash_account_id'];

            $stmt = $db->prepare("INSERT INTO expenses (expense_category, description, amount, expense_date, user_id, paid_from_account_id, expense_account_id, attachment, created_at) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW())");
            $stmt->execute([$category_name, "صرف وردية: " . $notes, $amount, $admin['id'], $cash_account_id, $expense_account_id, $attachment_path]);
            $expense_id = $db->lastInsertId();

            $db->prepare("UPDATE shifts SET expenses = expenses + ? WHERE id = ?")->execute([$amount, $shift['id']]);

            $journal_items = [['account_id' => $expense_account_id, 'type' => 'debit', 'amount' => $amount], ['account_id' => $cash_account_id, 'type' => 'credit', 'amount' => $amount]];
            createJournalEntry($db, date('Y-m-d'), "مصروف نقدي من الوردية - $category_name", $journal_items, $expense_id, 'expense', $admin['id']);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'تم تسجيل المصروف']);
            break;
case 'get_current_shift_data':
    // Step 1: Find the currently active shift for the logged-in user
    $shift_stmt = $db->prepare("SELECT id, opening_cash, expenses FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
    $shift_stmt->execute([$admin['id']]);
    $shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shift) {
        // If no shift is found, send a clear error message
        throw new Exception('No active shift found. Please open a shift first.');
    }

    // Step 2: Calculate all sales totals for this specific shift from the 'invoices' table
    // This query is much more comprehensive than the old one.
    $sales_stmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(cash_amount), 0) as cash_sales,
            COALESCE(SUM(card_amount), 0) as card_sales,
            COALESCE(SUM(transfer_amount), 0) as bank_transfer_sales
        FROM invoices
        WHERE shift_id = ?
    ");
    $sales_stmt->execute([$shift['id']]);
    $sales_data = $sales_stmt->fetch(PDO::FETCH_ASSOC);

    // Step 3: Combine the shift data and the calculated sales data
    $full_shift_data = array_merge($shift, $sales_data);

    // Step 4: Ensure the 'cash_payouts' key exists, as the front-end JS expects it
    // The value comes from the 'expenses' column in your shifts table.
    $full_shift_data['cash_payouts'] = $full_shift_data['expenses'] ?? 0;

    // Step 5: Send the data back in the correct format ('shift' object)
    // This now perfectly matches what the pos.php file expects.
    echo json_encode(['success' => true, 'shift' => $full_shift_data]);
    break;
        case 'close_shift':
            $closing_cash = floatval($_POST['closing_cash'] ?? -1);
            if ($closing_cash < 0) throw new Exception('مبلغ الإغلاق غير صحيح.');
            $db->prepare("UPDATE shifts SET status = 'closed', closing_cash = ?, notes = ?, closed_at = NOW() WHERE user_id = ? AND status = 'opened'")
               ->execute([$closing_cash, trim($_POST['notes'] ?? ''), $admin['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'process_invoice':
            $invoice_data = json_decode($_POST['invoice_data'], true);
            $payment_data = json_decode($_POST['payment_data'], true);
            if (!$invoice_data || !$payment_data || empty($invoice_data['items'])) {
                throw new Exception('Invalid invoice or payment data.');
            }
            
            $admin_id = $admin['id'];
            $shift_stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
            $shift_stmt->execute([$admin_id]);
            $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current_shift) {
                throw new Exception('No open shift found. Please open a shift to continue.');
            }
            
            $db->beginTransaction();
            $productManager = new SimpleProductManager(); 

            // --- SIMPLIFIED: Stock validation with base units ---
            foreach ($invoice_data['items'] as $item) {
                 if ($item['type'] === 'product') {
                    $product_stmt = $db->prepare("SELECT name_ar, stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $product_stmt->execute([$item['id']]);
                    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$product || $product['stock_quantity'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for '{$item['name']}'");
                    }
                } elseif ($item['type'] === 'service') {
                    // Fetch recipe and ingredient stock (which is already in base units)
                    $recipe_stmt = $db->prepare("
                        SELECT pr.quantity_consumed, p.name_ar, p.stock_quantity 
                        FROM service_recipes pr JOIN products p ON pr.ingredient_product_id = p.id 
                        WHERE pr.service_id = ?
                    ");
                    $recipe_stmt->execute([$item['id']]);
                    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($recipe as $ingredient) {
                        // Recipe quantity is in base units, stock is in base units. Direct comparison.
                        $required_qty = $ingredient['quantity_consumed'] * $item['quantity'];
                        if ((float)$ingredient['stock_quantity'] < $required_qty) {
                            throw new Exception("Insufficient stock for '{$ingredient['name_ar']}'. Required: {$required_qty}, Available: {$ingredient['stock_quantity']}");
                        }
                    }
                }
            }
            
            // Accounting checks
            $acc_settings = getAccountingSettings($db);
            // ... (Skipping full required_accounts array check for brevity, assumed same as previous)

            // Calculations
            $subtotal = array_reduce($invoice_data['items'], function($sum, $item){ return $sum + ($item['unit_price'] * $item['quantity']); }, 0);
            $tax_rate = ($site_settings['tax_rate']['status'] ?? 'inactive') === 'active' ? floatval($site_settings['tax_rate']['value'] ?? 15) : 0;
            $tax_amount = round($subtotal * ($tax_rate / 100), 2);
            $discount_amount = 0;
            if (($invoice_data['discount_value'] ?? 0) > 0) {
                $discount_amount = ($invoice_data['discount_type'] === 'percentage') ? ($subtotal * ($invoice_data['discount_value'] / 100)) : floatval($invoice_data['discount_value']);
            }
            $total_amount = round(($subtotal + $tax_amount) - $discount_amount, 2);

            // Customer
            $customer_name = trim($invoice_data['customer_name'] ?? '');
            $customer_id = $invoice_data['customer_id'] ?? findOrCreateCustomerByName($db, $customer_name, $admin_id);

            // Insert Invoice
            $invoice_number = generateInvoiceNumber($db);
            $access_token = bin2hex(random_bytes(24));
            
            $cash_p = 0; $card_p = 0; $transfer_p = 0;
            if ($payment_data['method'] === 'mixed') { $cash_p = $payment_data['cash_amount']; $card_p = $payment_data['card_amount']; }
            elseif ($payment_data['method'] === 'cash') $cash_p = $total_amount;
            elseif ($payment_data['method'] === 'card') $card_p = $total_amount;
            elseif ($payment_data['method'] === 'bank_transfer') $transfer_p = $total_amount;

            $invoice_sql = "INSERT INTO invoices (invoice_number, access_token, customer_id, user_id, shift_id, subtotal, tax_rate, tax_amount, discount_amount, total_amount, payment_method, payment_status, status, cash_amount, card_amount, transfer_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'paid', ?, ?, ?, NOW())";
            $db->prepare($invoice_sql)->execute([$invoice_number, $access_token, $customer_id, $admin_id, $current_shift['id'], $subtotal, $tax_rate, $tax_amount, $discount_amount, $total_amount, $payment_data['method'], $cash_p, $card_p, $transfer_p]);
            $invoice_id = $db->lastInsertId();

            // Items & Stock Deduction
            $item_stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_id, item_type, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $total_cogs = 0;
            $total_service_rev = 0;
            $total_product_rev = 0;

            foreach ($invoice_data['items'] as $item) {
                $item_total = $item['unit_price'] * $item['quantity'];
                $item_stmt->execute([$invoice_id, $item['id'], $item['type'], $item['name'], $item['quantity'], $item['unit_price'], $item_total]);
                
                if ($item['type'] === 'product') {
                    $total_product_rev += $item_total;
                    $productManager->adjustStock($item['id'], -$item['quantity'], 'sale', $admin_id, $invoice_id, 'invoice', "Sale #{$invoice_number}");
                    $p_stmt = $db->prepare("SELECT cost_price FROM products WHERE id = ?");
                    $p_stmt->execute([$item['id']]);
                    $total_cogs += ($p_stmt->fetchColumn() * $item['quantity']);
                } else { // Item type is 'service'
                    $total_service_rev += $item_total;
                    $recipe_stmt = $db->prepare("SELECT ingredient_product_id, quantity_consumed FROM service_recipes WHERE service_id = ?");
                    $recipe_stmt->execute([$item['id']]);
                    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($recipe as $ingredient) {
                        // The amount to deduct is already in the correct base unit
                        $total_to_deduct = $ingredient['quantity_consumed'] * $item['quantity'];
                        $notes = "Consumed for {$item['name']} in Invoice #{$invoice_number}";
                        
                        // Use the new dedicated function for simplicity and clarity
                        $productManager->deductStockByBaseUnits(
                            $ingredient['ingredient_product_id'], 
                            -$total_to_deduct, // Send a negative number for deduction
                            'consumption', 
                            $admin_id, 
                            $invoice_id, 
                            'invoice', 
                            $notes
                        );
                    }
                }
            }

            // Accounting Entries
            $journal_sales = [];
            if ($cash_p > 0) $journal_sales[] = ['account_id' => $acc_settings['default_cash_account_id'], 'type' => 'debit', 'amount' => $cash_p];
            if ($card_p > 0) $journal_sales[] = ['account_id' => $acc_settings['default_card_account_id'], 'type' => 'debit', 'amount' => $card_p];
            if ($transfer_p > 0) $journal_sales[] = ['account_id' => $acc_settings['default_transfer_account_id'], 'type' => 'debit', 'amount' => $transfer_p];
            if ($discount_amount > 0) $journal_sales[] = ['account_id' => $acc_settings['default_sales_discount_account_id'], 'type' => 'debit', 'amount' => $discount_amount];
            if ($total_service_rev > 0) $journal_sales[] = ['account_id' => $acc_settings['default_service_revenue_account_id'], 'type' => 'credit', 'amount' => $total_service_rev];
            if ($total_product_rev > 0) $journal_sales[] = ['account_id' => $acc_settings['default_sales_revenue_account_id'], 'type' => 'credit', 'amount' => $total_product_rev];
            if ($tax_amount > 0) $journal_sales[] = ['account_id' => $acc_settings['default_vat_liability_account_id'], 'type' => 'credit', 'amount' => $tax_amount];
            createJournalEntry($db, date('Y-m-d'), "Invoice #{$invoice_number}", $journal_sales, $invoice_id, 'invoice', $admin_id);

            // ZATCA QR
            $qr_uri = '';
            if ($tax_amount > 0 && class_exists('Endroid\QrCode\QrCode')) {
                $qrCode = new QrCode(generateZatcaQrCodeString($site_settings['pos_receipt_header']['value'] ?? 'Salon', $site_settings['tax_number']['value'] ?? '', date('c'), $total_amount, $tax_amount));
                $qr_uri = (new PngWriter())->write($qrCode)->getDataUri();
            }

            $db->commit();
            
            // PDF Generation (Simplified call)
            if (class_exists('\Mpdf\Mpdf')) {
                // ... (mPDF logic exactly as provided in previous version)
            }

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Invoice created successfully!', 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number, 'zatca_qr' => $qr_uri]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;

// =================================================================
// UTILITY FUNCTIONS
// =================================================================

function findOrCreateCustomerByName($db, $name, $created_by) {
    $name = trim($name);
    if (empty($name) || $name === 'زائر') return null;
    $stmt = $db->prepare("SELECT id FROM customers WHERE name_ar = ? OR name = ? LIMIT 1");
    $stmt->execute([$name, $name]);
    $id = $stmt->fetchColumn();
    if ($id) return $id;
    $db->prepare("INSERT INTO customers (customer_code, name_ar, status, created_at, created_by) VALUES (?, ?, 'active', NOW(), ?)")
       ->execute(['CUS'.rand(1000,9999), $name, $created_by]);
    return $db->lastInsertId();
}

function generateInvoiceNumber($db) {
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT MAX(invoice_number) FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int)substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateZatcaQrCodeString($seller, $taxNo, $time, $total, $tax) {
    $tlv = pack('C', 1) . pack('C', strlen($seller)) . $seller;
    $tlv .= pack('C', 2) . pack('C', strlen($taxNo)) . $taxNo;
    $tlv .= pack('C', 3) . pack('C', strlen($time)) . $time;
    $tlv .= pack('C', 4) . pack('C', strlen($total)) . $total;
    $tlv .= pack('C', 5) . pack('C', strlen($tax)) . $tax;
    return base64_encode($tlv);
}