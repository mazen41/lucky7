<?php
// Disable error display and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php-error.log');

// Start output buffering to catch any unwanted output
ob_start();
ob_clean();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

// For AJAX requests, return JSON instead of redirecting
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    ob_end_flush();
    exit;
}

$admin = getCurrentAdmin();
if (!$admin || !isset($admin['id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid admin session']);
    ob_end_flush();
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Clear any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

// Helper function to get site setting
if (!function_exists('getSiteSetting')) {
    function getSiteSetting($key, $default = null) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Helper function to generate invoice number
function generateInvoiceNumber($db) {
    $date = date('Ymd');
    $query = "SELECT COUNT(*) + 1 as next_num FROM invoices WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return 'INV-' . $date . '-' . str_pad($result['next_num'], 4, '0', STR_PAD_LEFT);
}

try {
    switch ($action) {
        case 'process_invoice':
            // Include ZATCA helper if file exists
            if (file_exists('includes/zatca_helper.php')) {
                require_once 'includes/zatca_helper.php';
            }

            // Get and validate input data
            $invoice_data = json_decode($_POST['invoice_data'], true);
            $payment_data = json_decode($_POST['payment_data'], true);
            
            if (!$invoice_data || !$payment_data) {
                echo json_encode(['success' => false, 'message' => 'بيانات الفاتورة أو الدفع غير صحيحة']);
                exit;
            }

            // Validate required data
            if (empty($invoice_data['items'])) {
                echo json_encode(['success' => false, 'message' => 'لا توجد عناصر في الفاتورة']);
                exit;
            }

            // Get current open shift with debugging
            $admin_id = $admin['id'] ?? null;
            error_log("POS Debug: Admin ID = " . $admin_id);
            
            $shift_query = "SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1";
            $shift_stmt = $db->prepare($shift_query);
            $shift_stmt->execute([$admin_id]);
            $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("POS Debug: Found shift = " . ($current_shift ? 'YES' : 'NO'));
            if ($current_shift) {
                error_log("POS Debug: Shift ID = " . $current_shift['id']);
            }
            
            if (!$current_shift) {
                echo json_encode(['success' => false, 'message' => 'يجب فتح وردية أولاً']);
                exit;
            }

            // Check if cash payment requires open shift
            $pos_require_shift = getSiteSetting('pos_require_open_shift_for_cash', '1');
            if ($pos_require_shift && ($payment_data['method'] === 'cash' || $payment_data['cash_amount'] > 0)) {
                if (!$current_shift) {
                    echo json_encode(['success' => false, 'message' => 'يجب فتح وردية للدفع النقدي']);
                    exit;
                }
            }
            
            // Disable foreign key checks and start transaction
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->beginTransaction();
            
            try {
                // Calculate totals
                $subtotal = floatval($invoice_data['subtotal'] ?? 0);
                $discount_amount = floatval($invoice_data['discount_amount'] ?? 0);
                $tax_amount = floatval($invoice_data['tax_amount'] ?? 0);
                $tax_rate = floatval($invoice_data['tax_rate'] ?? 0);
                $total_amount = floatval($invoice_data['total'] ?? 0);
                    
                // Handle customer creation/update
                $customer_id = null;
                if (!empty($invoice_data['customer_phone'])) {
                    $customer_query = "SELECT id FROM customers WHERE phone = ?";
                    $customer_stmt = $db->prepare($customer_query);
                    $customer_stmt->execute([$invoice_data['customer_phone']]);
                    $existing_customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_customer) {
                        $customer_id = $existing_customer['id'];
                        if (!empty($invoice_data['customer_name'])) {
                            $update_customer_query = "UPDATE customers SET name = ?, total_spend = total_spend + ? WHERE id = ?";
                            $update_customer_stmt = $db->prepare($update_customer_query);
                            $update_customer_stmt->execute([$invoice_data['customer_name'], $total_amount, $customer_id]);
                        }
                    } else {
                        $create_customer_query = "INSERT INTO customers (name, phone, total_spend) VALUES (?, ?, ?)";
                        $create_customer_stmt = $db->prepare($create_customer_query);
                        
                        if (!$create_customer_stmt->execute([
                            $invoice_data['customer_name'] ?? '',
                            $invoice_data['customer_phone'],
                            floatval($total_amount)
                        ])) {
                            throw new Exception('Failed to create customer: ' . implode(', ', $create_customer_stmt->errorInfo()));
                        }
                        $customer_id = $db->lastInsertId();
                    }
                }
                    
                // Generate invoice number
                $invoice_number = generateInvoiceNumber($db);
                    
                // Validate discount code if provided
                $discount_code_id = null;
                if (!empty($invoice_data['discount_code'])) {
                    $code_query = "SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE())";
                    $code_stmt = $db->prepare($code_query);
                    $code_stmt->execute([$invoice_data['discount_code']]);
                    $discount_code = $code_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($discount_code) {
                        if ($discount_code['usage_limit'] && $discount_code['used_count'] >= $discount_code['usage_limit']) {
                            throw new Exception('كود الخصم وصل للحد الأقصى من الاستخدام');
                        }
                        $discount_code_id = $discount_code['id'];
                        
                        $update_code_query = "UPDATE discount_codes SET used_count = used_count + 1 WHERE id = ?";
                        $update_code_stmt = $db->prepare($update_code_query);
                        $update_code_stmt->execute([$discount_code_id]);
                    }
                }
                
                // Insert invoice without shift_id first to bypass constraint issues
                $invoice_query = "INSERT INTO invoices (invoice_number, customer_id, user_id, subtotal, discount_amount, discount_type, discount_code_id, tax_amount, tax_rate, total_amount, status, notes, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())";
                
                $invoice_stmt = $db->prepare($invoice_query);
                if (!$invoice_stmt->execute([
                    $invoice_number,
                    $customer_id,
                    $admin_id,
                    $subtotal,
                    $discount_amount,
                    $invoice_data['discount_type'] ?? 'amount',
                    $discount_code_id,
                    $tax_amount,
                    $tax_rate,
                    $total_amount,
                    $invoice_data['notes'] ?? ''
                ])) {
                    throw new Exception('Failed to create invoice: ' . implode(', ', $invoice_stmt->errorInfo()));
                }
                
                $invoice_id = $db->lastInsertId();
                error_log("POS Debug: Invoice created with ID: $invoice_id");
                
                // Update invoice with shift_id separately to bypass foreign key constraint
                if ($current_shift) {
                    $update_shift_query = "UPDATE invoices SET shift_id = ? WHERE id = ?";
                    $update_shift_stmt = $db->prepare($update_shift_query);
                    $update_shift_stmt->execute([$current_shift['id'], $invoice_id]);
                    error_log("POS Debug: Updated invoice with shift ID: " . $current_shift['id']);
                }
                
                // Insert invoice items
                foreach ($invoice_data['items'] as $item) {
                    $item_query = "INSERT INTO invoice_items (invoice_id, type, item_id, name, price, quantity, discount_amount, specialist_id, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $item_stmt = $db->prepare($item_query);
                    if (!$item_stmt->execute([
                        $invoice_id,
                        $item['type'],
                        $item['id'],
                        $item['name'],
                        floatval($item['price']),
                        intval($item['quantity']),
                        floatval($item['discount_amount'] ?? 0),
                        $item['specialist_id'] ?? null,
                        $item['notes'] ?? ''
                    ])) {
                        throw new Exception('Failed to create invoice item: ' . implode(', ', $item_stmt->errorInfo()));
                    }
                }
                
                // Process payment
                $payment_query = "INSERT INTO payments (invoice_id, method, amount, status, processed_at) VALUES (?, ?, ?, 'completed', NOW())";
                $payment_stmt = $db->prepare($payment_query);
                
                // Handle different payment methods
                if ($payment_data['method'] === 'mixed') {
                    if ($payment_data['cash_amount'] > 0) {
                        $payment_stmt->execute([$invoice_id, 'cash', floatval($payment_data['cash_amount'])]);
                    }
                    if ($payment_data['card_amount'] > 0) {
                        $payment_stmt->execute([$invoice_id, 'card', floatval($payment_data['card_amount'])]);
                    }
                    if ($payment_data['transfer_amount'] > 0) {
                        $payment_stmt->execute([$invoice_id, 'transfer', floatval($payment_data['transfer_amount'])]);
                    }
                } else {
                    $amount = $payment_data['cash_amount'] ?? $payment_data['card_amount'] ?? $payment_data['transfer_amount'] ?? $total_amount;
                    $payment_stmt->execute([$invoice_id, $payment_data['method'], floatval($amount)]);
                }
                
                // Update shift totals if shift exists
                if ($current_shift) {
                    $shift_update_query = "UPDATE shifts SET 
                                          total_sales = total_sales + ?,
                                          total_cash_sales = total_cash_sales + ?,
                                          total_card_sales = total_card_sales + ?,
                                          total_transfer_sales = total_transfer_sales + ?
                                          WHERE id = ?";
                    
                    $cash_amount = ($payment_data['method'] === 'cash' || $payment_data['method'] === 'mixed') ? floatval($payment_data['cash_amount'] ?? 0) : 0;
                    $card_amount = ($payment_data['method'] === 'card' || $payment_data['method'] === 'mixed') ? floatval($payment_data['card_amount'] ?? 0) : 0;
                    $transfer_amount = ($payment_data['method'] === 'transfer' || $payment_data['method'] === 'mixed') ? floatval($payment_data['transfer_amount'] ?? 0) : 0;
                    
                    $shift_update_stmt = $db->prepare($shift_update_query);
                    $shift_update_stmt->execute([
                        $total_amount,
                        $cash_amount,
                        $card_amount, 
                        $transfer_amount,
                        $current_shift['id']
                    ]);
                }
                
                $db->commit();
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                // ZATCA integration for invoice QR code
                $zatca_enabled = getSiteSetting('pos_zatca_enabled', '0');
                $qr_code_data = '';
                $xml_path = '';
                
                if ($zatca_enabled == '1' && function_exists('generateZatcaQRCode')) {
                    try {
                        $zatca_invoice = [
                            'invoice_number' => $invoice_number,
                            'created_at' => date('Y-m-d H:i:s'),
                            'seller_name' => getSiteSetting('pos_business_name', 'MAI Salon'),
                            'vat_number' => getSiteSetting('pos_business_tax_number', '123456789012345'),
                            'customer_name' => $invoice_data['customer_name'] ?? '',
                            'customer_phone' => $invoice_data['customer_phone'] ?? '',
                            'items' => $invoice_data['items'] ?? [],
                            'subtotal' => $subtotal,
                            'tax_amount' => $tax_amount,
                            'total_amount' => $total_amount
                        ];
                        
                        if (function_exists('generateAndSaveZatcaXML')) {
                            $xml_path = generateAndSaveZatcaXML($zatca_invoice, $invoice_id);
                        }
                        
                        $qr_code_data = generateZatcaQRCode($zatca_invoice);
                    } catch (Exception $zatca_error) {
                        error_log('ZATCA Error: ' . $zatca_error->getMessage());
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم إنشاء الفاتورة بنجاح',
                    'invoice_id' => $invoice_id,
                    'invoice_number' => $invoice_number,
                    'zatca_qr' => $qr_code_data,
                    'zatca_xml' => $xml_path
                ]);
                    
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                error_log('POS Invoice Error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'خطأ في إنشاء الفاتورة: ' . $e->getMessage()]);
            }
            break;

        case 'validate_discount_code':
            $code = $_POST['code'] ?? '';
            
            $code_query = "SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE())";
            $code_stmt = $db->prepare($code_query);
            $code_stmt->execute([$code]);
            $discount_code = $code_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$discount_code) {
                echo json_encode(['success' => false, 'message' => 'كود الخصم غير صحيح أو منتهي الصلاحية']);
                exit;
            }
            
            if ($discount_code['usage_limit'] && $discount_code['used_count'] >= $discount_code['usage_limit']) {
                echo json_encode(['success' => false, 'message' => 'كود الخصم وصل للحد الأقصى من الاستخدام']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'discount_code' => $discount_code,
                'message' => 'كود الخصم صحيح'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'إجراء غير صحيح']);
            break;
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    error_log('POS Action Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى.'
    ]);
}

ob_end_flush();
exit;
?>
