<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'includes/thermal_receipt_template.php';
require_once 'includes/zatca_qr_generator.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

// Get invoice ID from URL
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die('Invoice ID is required');
}

try {
    // Get invoice data with all related information
    $invoice_query = "
        SELECT i.*, 
               COALESCE(c.name_ar, c.name_en) as customer_name, c.phone as customer_phone,
               u.username as cashier_name,
               s.id as shift_id
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN admin_users u ON i.user_id = u.id
        LEFT JOIN shifts s ON i.shift_id = s.id
        WHERE i.id = ?
    ";
    
    $invoice_stmt = $db->prepare($invoice_query);
    $invoice_stmt->execute([$invoice_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die('Invoice not found');
    }
    
    // Get invoice items with specialist information
    $items_query = "
        SELECT ii.*, 
               tm.name_ar as specialist_name,
               CASE 
                   WHEN ii.type = 'service' THEN s.title_ar
                   WHEN ii.type = 'product' THEN p.name_ar
                   ELSE ii.name
               END as item_name
        FROM invoice_items ii
        LEFT JOIN team_members tm ON ii.specialist_id = tm.id
        LEFT JOIN services s ON ii.type = 'service' AND ii.item_id = s.id
        LEFT JOIN products p ON ii.type = 'product' AND ii.item_id = p.id
        WHERE ii.invoice_id = ?
        ORDER BY ii.id
    ";
    
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$invoice_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment information
    $payments_query = "SELECT * FROM payments WHERE invoice_id = ?";
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->execute([$invoice_id]);
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format payment method
    $payment_method = 'cash';
    $payment_details = [];
    
    if (count($payments) > 1) {
        $payment_method = 'mixed';
        foreach ($payments as $payment) {
            $payment_details[] = [
                'method' => $payment['method'],
                'amount' => $payment['amount']
            ];
        }
    } elseif (count($payments) == 1) {
        $payment_method = $payments[0]['method'];
    }
    
    // Format items for receipt
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'name' => $item['item_name'] ?: $item['name'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => $item['price'] * $item['quantity'],
            'discount_amount' => $item['discount_amount'] ?? 0,
            'specialist_name' => $item['specialist_name'],
            'notes' => $item['notes']
        ];
    }
    
    // Get business settings
    $settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'pos_%'";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings_data = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $business_settings = [
        'name' => 'صالون MAI للجمال',
        'name_en' => 'MAI Beauty Salon',
        'address' => 'الرياض، المملكة العربية السعودية',
        'phone' => '+966 11 123 4567',
        'email' => 'info@maisalon.com',
        'vat_number' => '123456789012345',
        'cr_number' => '1010123456'
    ];
    
    // Override with actual settings if available
    foreach ($settings_data as $setting) {
        switch ($setting['setting_key']) {
            case 'pos_business_name':
                $business_settings['name'] = $setting['setting_value'];
                break;
            case 'pos_business_address':
                $business_settings['address'] = $setting['setting_value'];
                break;
            case 'pos_business_phone':
                $business_settings['phone'] = $setting['setting_value'];
                break;
            case 'pos_business_email':
                $business_settings['email'] = $setting['setting_value'];
                break;
            case 'pos_business_tax_number':
                $business_settings['vat_number'] = $setting['setting_value'];
                break;
        }
    }
    
    // Generate ZATCA QR code
    $zatca_qr = '';
    $zatca_qr_data = '';
    
    // Always generate ZATCA QR code for invoices
    $zatca_data = ZatcaQRGenerator::formatInvoiceForQR($invoice, $business_settings);
    $zatca_qr_data = ZatcaQRGenerator::generateQRData($zatca_data);
    
    if ($zatca_qr_data) {
        // Generate QR image using Google Charts API
        $zatca_qr = ZatcaQRGenerator::generateQRImage($zatca_qr_data, 120);
        
        // Debug: Log QR generation
        error_log("ZATCA QR Generated - Data: " . substr($zatca_qr_data, 0, 50) . "...");
        error_log("ZATCA QR Image Length: " . strlen($zatca_qr));
    } else {
        error_log("ZATCA QR Data generation failed");
    }
    
    // Prepare invoice data for template
    $invoice_data = [
        'invoice_number' => $invoice['invoice_number'],
        'created_at' => $invoice['created_at'],
        'customer_name' => $invoice['customer_name'],
        'customer_phone' => $invoice['customer_phone'],
        'cashier_name' => $invoice['cashier_name'],
        'shift_id' => $invoice['shift_id'],
        'items' => $formatted_items,
        'subtotal' => $invoice['subtotal'],
        'discount_amount' => $invoice['discount_amount'],
        'discount_code' => $invoice['discount_code_id'] ? 'كود خصم مطبق' : null,
        'tax_amount' => $invoice['tax_amount'],
        'tax_rate' => $invoice['tax_rate'],
        'total_amount' => $invoice['total_amount'],
        'payment_method' => $payment_method,
        'payment_details' => $payment_details,
        'zatca_qr' => $zatca_qr,
        'zatca_qr_data' => $zatca_qr_data
    ];
    
    // Generate and output receipt
    echo generateThermalReceipt($invoice_data, $business_settings);
    
} catch (Exception $e) {
    error_log('Print Receipt Error: ' . $e->getMessage());
    die('Error generating receipt: ' . $e->getMessage());
}
?>
