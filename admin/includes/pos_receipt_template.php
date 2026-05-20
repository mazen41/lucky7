<?php
/**
 * POS Receipt Template with ZATCA QR Code Support
 * MAI Salon - 88mm Thermal Printer Format
 */

require_once __DIR__ . '/zatca_helper.php';

/**
 * Generate receipt HTML for printing
 * 
 * @param array $invoice Invoice data
 * @param array $items Invoice items
 * @param array $payment Payment data
 * @return string HTML for receipt
 */
function generateReceiptHTML($invoice, $items, $payment) {
    global $db;
    
    // Get settings
    $settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'pos_%'";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Business info
    $business_name = $settings['pos_receipt_header'] ?? 'صالون MAI للجمال';
    $business_address = $settings['pos_business_address'] ?? '';
    $business_phone = $settings['pos_business_phone'] ?? '';
    $business_email = $settings['pos_business_email'] ?? '';
    $business_tax_number = $settings['pos_business_tax_number'] ?? '';
    $receipt_footer = $settings['pos_receipt_footer'] ?? 'شكراً لزيارتكم - نتطلع لخدمتكم مرة أخرى';
    
    // Format date
    $invoice_date = date('Y-m-d H:i', strtotime($invoice['created_at'] ?? 'now'));
    
    // Start receipt HTML
    $html = '
    <div class="receipt" dir="rtl">
        <div class="text-center mb-4">
            <h2 class="font-bold text-lg">' . htmlspecialchars($business_name) . '</h2>';
    
    if (!empty($business_address)) {
        $html .= '<p class="text-sm">' . htmlspecialchars($business_address) . '</p>';
    }
    
    if (!empty($business_phone)) {
        $html .= '<p class="text-sm">' . htmlspecialchars($business_phone) . '</p>';
    }
    
    if (!empty($business_email)) {
        $html .= '<p class="text-sm">' . htmlspecialchars($business_email) . '</p>';
    }
    
    if (!empty($business_tax_number)) {
        $html .= '<p class="text-sm">الرقم الضريبي: ' . htmlspecialchars($business_tax_number) . '</p>';
    }
    
    $html .= '
        </div>
        
        <div class="border-t border-b py-2 mb-2">
            <div class="flex justify-between">
                <span>رقم الفاتورة:</span>
                <span>' . htmlspecialchars($invoice['invoice_number'] ?? '') . '</span>
            </div>
            <div class="flex justify-between">
                <span>التاريخ:</span>
                <span>' . $invoice_date . '</span>
            </div>
            <div class="flex justify-between">
                <span>الموظف:</span>
                <span>' . htmlspecialchars($invoice['cashier_name'] ?? '') . '</span>
            </div>';
    
    // Customer info if available
    if (!empty($invoice['customer_name']) || !empty($invoice['customer_phone'])) {
        $html .= '<div class="border-t pt-2 mt-2">';
        
        if (!empty($invoice['customer_name'])) {
            $html .= '
                <div class="flex justify-between">
                    <span>العميل:</span>
                    <span>' . htmlspecialchars($invoice['customer_name']) . '</span>
                </div>';
        }
        
        if (!empty($invoice['customer_phone'])) {
            $html .= '
                <div class="flex justify-between">
                    <span>الهاتف:</span>
                    <span>' . htmlspecialchars($invoice['customer_phone']) . '</span>
                </div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
        </div>
        
        <div class="items mb-2">';
    
    // Items
    foreach ($items as $item) {
        $html .= '
            <div class="mb-2">
                <div class="flex justify-between">
                    <span class="font-medium">' . htmlspecialchars($item['item_name']) . '</span>
                    <span>' . number_format($item['total_price'], 2) . '</span>
                </div>
                <div class="text-sm text-gray-600 flex justify-between">
                    <span>' . $item['quantity'] . ' × ' . number_format($item['unit_price'], 2) . '</span>';
        
        if (!empty($item['specialist_name'])) {
            $html .= '<span>' . htmlspecialchars($item['specialist_name']) . '</span>';
        }
        
        $html .= '
                </div>
            </div>';
    }
    
    $html .= '
        </div>
        
        <div class="border-t pt-2 mt-2">
            <div class="flex justify-between">
                <span>المجموع الفرعي:</span>
                <span>' . number_format($invoice['subtotal'] ?? 0, 2) . ' ر.س</span>
            </div>';
    
    // Discount if applicable
    if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0) {
        $html .= '
            <div class="flex justify-between text-red-600">
                <span>الخصم:</span>
                <span>-' . number_format($invoice['discount_amount'], 2) . ' ر.س</span>
            </div>';
    }
    
    // Tax if applicable
    if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0) {
        $html .= '
            <div class="flex justify-between">
                <span>الضريبة (' . ($invoice['tax_rate'] ?? 15) . '%):</span>
                <span>' . number_format($invoice['tax_amount'], 2) . ' ر.س</span>
            </div>';
    }
    
    $html .= '
            <div class="flex justify-between font-bold border-t pt-1 mt-1">
                <span>المجموع الكلي:</span>
                <span>' . number_format($invoice['total_amount'] ?? 0, 2) . ' ر.س</span>
            </div>
            <div class="flex justify-between mt-1">
                <span>طريقة الدفع:</span>
                <span>' . getPaymentMethodArabic($payment['method'] ?? 'cash') . '</span>
            </div>
        </div>';
    
    // Generate ZATCA QR code if enabled
    if (isset($settings['pos_zatca_enabled']) && $settings['pos_zatca_enabled'] == '1') {
        $qr_code = generateZatcaQRCode($invoice);
        if (!empty($qr_code)) {
            $html .= '
            <div class="text-center mt-4">
                <p class="text-sm mb-2">رمز الفاتورة الإلكترونية</p>
                ' . $qr_code . '
            </div>';
        }
    }
    
    // Footer
    $html .= '
        <div class="text-center mt-4 text-sm">
            <p>' . htmlspecialchars($receipt_footer) . '</p>
        </div>
    </div>';
    
    return $html;
}

/**
 * Get Arabic payment method name
 * 
 * @param string $method Payment method
 * @return string Arabic payment method name
 */
function getPaymentMethodArabic($method) {
    switch ($method) {
        case 'cash':
            return 'نقداً';
        case 'card':
            return 'بطاقة';
        case 'transfer':
            return 'تحويل بنكي';
        case 'mixed':
            return 'مختلط';
        default:
            return $method;
    }
}
