<?php
/**
 * POS Actions Backend - Fixed Implementation
 * Handles all POS operations including invoice creation, shift management, and payment processing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'admin/auth.php';
require_once 'config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get current admin user
try {
    $admin = getCurrentAdmin();
    $user_id = $admin['id'] ?? 1;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_invoice':
        createInvoice($db, $user_id);
        break;
        
    case 'open_shift':
        openShift($db, $user_id);
        break;
        
    case 'close_shift':
        closeShift($db, $user_id);
        break;
        
    case 'search_invoices':
        searchInvoices($db);
        break;
        
    case 'validate_discount':
        validateDiscount($db);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        break;
}

/**
 * Create Invoice Function
 */
function createInvoice($db, $user_id) {
    try {
        // Validate required fields
        $required_fields = ['subtotal', 'tax_amount', 'total_amount', 'payment_method', 'amount_paid'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Check if shift is open
        $shift_check = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
        $shift_check->execute([$user_id]);
        $current_shift = $shift_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_shift) {
            throw new Exception('No open shift found. Please open a shift first.');
        }
        
        // Generate invoice number
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check if invoice number exists (unlikely but safe)
        $number_check = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
        $number_check->execute([$invoice_number]);
        if ($number_check->fetch()) {
            $invoice_number = 'INV-' . date('YmdHis') . '-' . rand(100, 999);
        }
        
        // Extract data
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_phone = $_POST['customer_phone'] ?? '';
        $subtotal = floatval($_POST['subtotal']);
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $tax_amount = floatval($_POST['tax_amount']);
        $total_amount = floatval($_POST['total_amount']);
        $payment_method = $_POST['payment_method'];
        $amount_paid = floatval($_POST['amount_paid']);
        $change_amount = max(0, $amount_paid - $total_amount);
        
        // Validate payment amount
        if ($amount_paid < $total_amount) {
            throw new Exception('Payment amount is less than total amount');
        }
        
        // Parse items
        $items = [];
        if (isset($_POST['items'])) {
            $items_json = $_POST['items'];
            $items = json_decode($items_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid items data');
            }
        }
        
        if (empty($items)) {
            throw new Exception('No items in invoice');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert invoice
        $invoice_query = "INSERT INTO invoices (
            invoice_number, customer_name, customer_phone, subtotal, 
            discount_amount, tax_amount, total_amount, payment_method, 
            amount_paid, change_amount, user_id, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid')";
        
        $invoice_stmt = $db->prepare($invoice_query);
        $invoice_stmt->execute([
            $invoice_number, $customer_name, $customer_phone, $subtotal,
            $discount_amount, $tax_amount, $total_amount, $payment_method,
            $amount_paid, $change_amount, $user_id
        ]);
        
        $invoice_id = $db->lastInsertId();
        
        // Insert invoice items
        $item_query = "INSERT INTO invoice_items (
            invoice_id, item_type, item_id, item_name, quantity, 
            unit_price, total_price, specialist_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $db->prepare($item_query);
        
        foreach ($items as $item) {
            $item_stmt->execute([
                $invoice_id,
                $item['type'],
                $item['id'],
                $item['name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                $item['specialist_id'] ?? null
            ]);
        }
        
        // Update shift total sales
        $update_shift = $db->prepare("UPDATE shifts SET total_sales = total_sales + ? WHERE id = ?");
        $update_shift->execute([$total_amount, $current_shift['id']]);
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Invoice created successfully',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'change_amount' => $change_amount
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Open Shift Function
 */
function openShift($db, $user_id) {
    try {
        // Check if there's already an open shift
        $check_shift = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'opened'");
        $check_shift->execute([$user_id]);
        
        if ($check_shift->fetch()) {
            throw new Exception('There is already an open shift. Please close it first.');
        }
        
        $opening_cash = floatval($_POST['opening_cash'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        // Insert new shift
        $shift_query = "INSERT INTO shifts (user_id, opening_cash, notes, status) VALUES (?, ?, ?, 'opened')";
        $shift_stmt = $db->prepare($shift_query);
        $shift_stmt->execute([$user_id, $opening_cash, $notes]);
        
        $shift_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Shift opened successfully',
            'shift_id' => $shift_id
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Close Shift Function
 */
function closeShift($db, $user_id) {
    try {
        // Find open shift
        $shift_query = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
        $shift_query->execute([$user_id]);
        $shift = $shift_query->fetch(PDO::FETCH_ASSOC);
        
        if (!$shift) {
            throw new Exception('No open shift found');
        }
        
        $closing_cash = floatval($_POST['closing_cash'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        // Update shift
        $update_query = "UPDATE shifts SET 
            closing_cash = ?, 
            notes = CONCAT(COALESCE(notes, ''), ?, ?), 
            status = 'closed', 
            closed_at = NOW() 
            WHERE id = ?";
        
        $note_separator = empty($shift['notes']) ? '' : '\n---\n';
        $closing_note = "Closed: $notes";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$closing_cash, $note_separator, $closing_note, $shift['id']]);
        
        // Calculate discrepancy
        $expected_cash = $shift['opening_cash'] + $shift['total_sales'];
        $discrepancy = $closing_cash - $expected_cash;
        
        echo json_encode([
            'success' => true,
            'message' => 'Shift closed successfully',
            'shift_id' => $shift['id'],
            'opening_cash' => $shift['opening_cash'],
            'total_sales' => $shift['total_sales'],
            'expected_cash' => $expected_cash,
            'closing_cash' => $closing_cash,
            'discrepancy' => $discrepancy
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Search Invoices Function
 */
function searchInvoices($db) {
    try {
        $query = $_POST['query'] ?? '';
        
        if (strlen($query) < 3) {
            echo json_encode(['success' => true, 'invoices' => []]);
            return;
        }
        
        $search_query = "SELECT * FROM invoices 
                        WHERE invoice_number LIKE ? 
                        OR customer_name LIKE ? 
                        OR customer_phone LIKE ? 
                        ORDER BY created_at DESC 
                        LIMIT 10";
        
        $search_param = "%$query%";
        $search_stmt = $db->prepare($search_query);
        $search_stmt->execute([$search_param, $search_param, $search_param]);
        
        $invoices = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'invoices' => $invoices
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Validate Discount Function
 */
function validateDiscount($db) {
    try {
        $discount_code = $_POST['discount_code'] ?? '';
        
        if (empty($discount_code)) {
            throw new Exception('Discount code is required');
        }
        
        $discount_query = "SELECT * FROM discount_codes 
                          WHERE code = ? AND is_active = 1 
                          AND (expiry_date IS NULL OR expiry_date > NOW())";
        
        $discount_stmt = $db->prepare($discount_query);
        $discount_stmt->execute([$discount_code]);
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$discount) {
            throw new Exception('Invalid or expired discount code');
        }
        
        echo json_encode([
            'success' => true,
            'discount' => $discount
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
