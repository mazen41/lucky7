<?php
// --- START: Standard Headers and Includes ---
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once 'auth.php'; // For getting current user and checking permissions
require_once '../config/database.php'; // For database connection
require_once 'users_functions.php'; // For getCurrentAdmin()

checkAdminAuth(); // Ensure the user is logged in
$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// --- END: Standard Headers and Includes ---

$action = $_POST['action'] ?? '';
$admin_id = $admin['id']; // Use the authenticated admin's ID

/**
 * HELPER FUNCTIONS
 */

if (!function_exists('getSiteSetting')) {
    function getSiteSetting($db, $key, $default = null) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? AND status = 'active'");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            error_log("Failed to get site setting '$key': " . $e->getMessage());
            return $default;
        }
    }
}

function generateInvoiceNumber($db) {
    $date = date('Ymd');
    $query = "SELECT COUNT(*) + 1 as next_num FROM invoices WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return 'INV-' . $date . '-' . str_pad($result['next_num'], 4, '0', STR_PAD_LEFT);
}

// --- REMOVED: generateReturnNumber() function was not used. ---

/**
 * MAIN ACTION ROUTER
 */

try {
    switch ($action) {
        case 'process_invoice':
            if (!hasPermission('pos.create_invoice')) {
                throw new Exception('ليس لديك الصلاحية لإنشاء فاتورة.');
            }

            $invoice_data = json_decode($_POST['invoice_data'], true);
            $payment_data = json_decode($_POST['payment_data'], true);
            $tax_rate = floatval(getSiteSetting($db, 'tax_rate', 15));

            if (empty($invoice_data['items'])) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن إنشاء فاتورة فارغة.']);
                exit;
            }

            $shift_query = "SELECT id FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1";
            $shift_stmt = $db->prepare($shift_query);
            $shift_stmt->execute([$admin_id]);
            $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current_shift) {
                echo json_encode(['success' => false, 'message' => 'يجب فتح وردية أولاً قبل إنشاء الفواتير.']);
                exit;
            }

            $db->beginTransaction();

            // 1. Server-side calculation to prevent tampering
            $subtotal = 0;
            foreach ($invoice_data['items'] as $item) {
                $subtotal += (floatval($item['unit_price']) * intval($item['quantity']));
            }

            $discount_amount = floatval($invoice_data['discount_amount'] ?? 0);
            
            // Tax is calculated on the subtotal (added on top)
            $tax_amount = $subtotal * ($tax_rate / 100);
            $total_amount = $subtotal + $tax_amount - $discount_amount;
            $total_amount = max(0, $total_amount); // Ensure total isn't negative

            // 2. Insert Invoice
            $invoice_no = generateInvoiceNumber($db);
            $invoice_query = "INSERT INTO invoices (
                invoice_number, shift_id, customer_name, subtotal, 
                discount_amount, tax_rate, tax_amount, total_amount, 
                payment_method, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, NOW())";
            
            $stmt = $db->prepare($invoice_query);
            $stmt->execute([
                $invoice_no,
                $current_shift['id'],
                $invoice_data['customer_name'] ?? 'عميل نقدي',
                $subtotal,
                $discount_amount,
                $tax_rate,
                $tax_amount,
                $total_amount,
                $payment_data['method'] ?? 'cash',
                $admin_id
            ]);
            $invoice_id = $db->lastInsertId();

            // 3. Insert Invoice Items
            $item_stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_id, item_type, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($invoice_data['items'] as $item) {
                $item_total = floatval($item['unit_price']) * intval($item['quantity']);
                $item_stmt->execute([
                    $invoice_id, $item['id'], $item['type'], $item['name'],
                    $item['quantity'], $item['unit_price'], $item_total
                ]);
            }

            // 4. Record Payment details
            $payment_stmt = $db->prepare("INSERT INTO payments (invoice_id, method, amount, cash_amount, card_amount, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->execute([
                $invoice_id, $payment_data['method'], $total_amount,
                $payment_data['cash_amount'] ?? 0,
                $payment_data['card_amount'] ?? 0,
                $admin_id
            ]);

            $db->commit();

            // Optional ZATCA QR Logic can be added here if needed
            $qr_code_data = ""; 

            echo json_encode([
                'success' => true, 
                'invoice_id' => $invoice_id, 
                'invoice_number' => $invoice_no,
                'zatca_qr' => $qr_code_data
            ]);
            break;

        case 'get_shift_summary':
            if (!hasPermission('pos.manage_shifts')) {
                throw new Exception('ليس لديك الصلاحية لإدارة الورديات.');
            }
            $shift_stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
            $shift_stmt->execute([$admin_id]);
            $shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'لا توجد وردية مفتوحة حالياً.']);
                exit;
            }

            // Calculate cash payments made during this shift
            $cash_sales_stmt = $db->prepare("SELECT SUM(p.cash_amount) as total_cash FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.shift_id = ?");
            $cash_sales_stmt->execute([$shift['id']]);
            $total_cash_sales = $cash_sales_stmt->fetchColumn() ?? 0;
            
            $expected_cash = $shift['opening_cash'] + $total_cash_sales;

            echo json_encode([
                'success' => true,
                'summary' => [
                    'opening_cash' => floatval($shift['opening_cash']),
                    'expected_cash' => floatval($expected_cash)
                ]
            ]);
            break;

        case 'close_shift':
            if (!hasPermission('pos.manage_shifts')) {
                throw new Exception('ليس لديك الصلاحية لإدارة الورديات.');
            }
            $closing_cash = floatval($_POST['closing_cash'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            $shift_stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1");
            $shift_stmt->execute([$admin_id]);
            $shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'لا توجد وردية مفتوحة للإغلاق.']);
                exit;
            }
            
            $cash_sales_stmt = $db->prepare("SELECT SUM(p.cash_amount) as total_cash FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.shift_id = ?");
            $cash_sales_stmt->execute([$shift['id']]);
            $total_cash_sales = $cash_sales_stmt->fetchColumn() ?? 0;
            $expected_cash = $shift['opening_cash'] + $total_cash_sales;
            $difference = $closing_cash - $expected_cash;

            $update = $db->prepare("UPDATE shifts SET closing_cash = ?, status = 'closed', closed_at = NOW(), difference = ?, notes = ? WHERE id = ?");
            $update->execute([$closing_cash, $difference, $notes, $shift['id']]);

            echo json_encode(['success' => true, 'message' => 'تم إغلاق الوردية بنجاح.']);
            break;

        case 'validate_discount_code':
            // This entire case is kept as it is a useful feature
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            
            $stmt = $db->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())");
            $stmt->execute([$code]);
            $discount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$discount) {
                echo json_encode(['success' => false, 'message' => 'كود الخصم غير صحيح أو منتهي الصلاحية.']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'تم تطبيق كود الخصم بنجاح.',
                'discount' => [
                    'code' => $code,
                    'type' => $discount['type'],
                    'value' => $discount['value']
                ]
            ]);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'الإجراء المطلوب غير صالح.']);
            break;
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("POS Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ فني: ' . $e->getMessage()]);
}
?>