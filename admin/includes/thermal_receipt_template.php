<?php
/**
 * 88mm Thermal Receipt Template
 * Optimized for Arabic RTL content with ZATCA compliance
 */

function generateThermalReceipt($invoice_data, $business_settings = null) {
    // Default business settings
    $business = $business_settings ?? [
        'name' => 'صالون MAI للجمال',
        'name_en' => 'MAI Beauty Salon',
        'address' => 'الرياض، المملكة العربية السعودية',
        'phone' => '+966 11 123 4567',
        'email' => 'info@maisalon.com',
        'vat_number' => '123456789012345',
        'cr_number' => '1010123456',
        'logo_path' => 'assets/images/mai-logo-thermal.png'
    ];
    
    $receipt_html = '
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة رقم ' . htmlspecialchars($invoice_data['invoice_number']) . '</title>
    <link rel="stylesheet" href="assets/css/thermal-print.css">
</head>
<body>
    <div class="receipt-container">
        <div class="receipt">
            <!-- Header Section -->
            <div class="receipt-header">
                <div class="business-name arabic-text">' . htmlspecialchars($business['name']) . '</div>
                <div class="business-name english-text" style="font-size: 10px;">' . htmlspecialchars($business['name_en']) . '</div>
                <div class="business-info arabic-text">' . htmlspecialchars($business['address']) . '</div>
                <div class="business-info english-text">' . htmlspecialchars($business['phone']) . '</div>
                <div class="business-info english-text">' . htmlspecialchars($business['email']) . '</div>
                <div class="business-info english-text">VAT: ' . htmlspecialchars($business['vat_number']) . '</div>
                <div class="business-info english-text">CR: ' . htmlspecialchars($business['cr_number']) . '</div>
            </div>

            <div class="divider"></div>

            <!-- Invoice Information -->
            <div class="receipt-info">
                <div class="info-line">
                    <span class="label">رقم الفاتورة:</span>
                    <span class="value english-text">' . htmlspecialchars($invoice_data['invoice_number']) . '</span>
                </div>
                <div class="info-line">
                    <span class="label">التاريخ:</span>
                    <span class="value english-text">' . date('d/m/Y H:i', strtotime($invoice_data['created_at'] ?? 'now')) . '</span>
                </div>
                <div class="info-line">
                    <span class="label">الموظف:</span>
                    <span class="value arabic-text">' . htmlspecialchars($invoice_data['cashier_name'] ?? 'موظف المبيعات') . '</span>
                </div>';
                
    if ($invoice_data['shift_id'] ?? false) {
        $receipt_html .= '
                <div class="info-line">
                    <span class="label">الوردية:</span>
                    <span class="value english-text">#' . htmlspecialchars($invoice_data['shift_id']) . '</span>
                </div>';
    }
    
    $receipt_html .= '
            </div>';

    // Customer Information (if available)
    if (!empty($invoice_data['customer_name']) || !empty($invoice_data['customer_phone'])) {
        $receipt_html .= '
            <div class="customer-info">
                <div class="text-center bold">معلومات العميل</div>';
        
        if (!empty($invoice_data['customer_name'])) {
            $receipt_html .= '
                <div class="info-line">
                    <span class="label">الاسم:</span>
                    <span class="value arabic-text">' . htmlspecialchars($invoice_data['customer_name']) . '</span>
                </div>';
        }
        
        if (!empty($invoice_data['customer_phone'])) {
            $receipt_html .= '
                <div class="info-line">
                    <span class="label">الهاتف:</span>
                    <span class="value english-text">' . htmlspecialchars($invoice_data['customer_phone']) . '</span>
                </div>';
        }
        
        $receipt_html .= '
            </div>';
    }

    // Items Section
    $receipt_html .= '
            <div class="receipt-items">
                <div class="items-header arabic-text">تفاصيل الفاتورة</div>';
    
    foreach ($invoice_data['items'] as $index => $item) {
        $receipt_html .= '
                <div class="item-line">
                    <div class="item-name arabic-text">
                        ' . htmlspecialchars($item['name']) . '
                        <div class="item-details">';
        
        if (!empty($item['specialist_name'])) {
            $receipt_html .= 'المختص: ' . htmlspecialchars($item['specialist_name']) . '<br>';
        }
        
        if (!empty($item['notes'])) {
            $receipt_html .= 'ملاحظات: ' . htmlspecialchars($item['notes']);
        }
        
        $receipt_html .= '
                        </div>
                    </div>
                    <div class="item-qty-price english-text">
                        ' . number_format($item['quantity'], 0) . ' × ' . number_format($item['price'], 2) . '
                    </div>
                    <div class="item-total english-text">
                        ' . number_format($item['total'], 2) . '
                    </div>
                </div>';
        
        // Add discount if applicable
        if (!empty($item['discount_amount']) && $item['discount_amount'] > 0) {
            $receipt_html .= '
                <div class="item-line discount-line">
                    <div class="item-name arabic-text">خصم على العنصر</div>
                    <div class="item-qty-price"></div>
                    <div class="item-total english-text">-' . number_format($item['discount_amount'], 2) . '</div>
                </div>';
        }
    }
    
    $receipt_html .= '
            </div>

            <div class="divider"></div>

            <!-- Totals Section -->
            <div class="receipt-totals">';
    
    // Subtotal
    $receipt_html .= '
                <div class="total-line">
                    <span class="total-label">المجموع الفرعي:</span>
                    <span class="total-amount english-text">' . number_format($invoice_data['subtotal'], 2) . ' ر.س</span>
                </div>';
    
    // Discount (if applicable)
    if (!empty($invoice_data['discount_amount']) && $invoice_data['discount_amount'] > 0) {
        $discount_text = 'خصم';
        if (!empty($invoice_data['discount_code'])) {
            $discount_text .= ' (كود: ' . htmlspecialchars($invoice_data['discount_code']) . ')';
        }
        $receipt_html .= '
                <div class="total-line discount-line">
                    <span class="total-label">' . $discount_text . ':</span>
                    <span class="total-amount english-text">-' . number_format($invoice_data['discount_amount'], 2) . ' ر.س</span>
                </div>';
    }
    
    // Tax (if applicable)
    if (!empty($invoice_data['tax_amount']) && $invoice_data['tax_amount'] > 0) {
        $tax_rate = $invoice_data['tax_rate'] ?? 15;
        $receipt_html .= '
                <div class="total-line tax-line">
                    <span class="total-label">ضريبة القيمة المضافة (' . number_format($tax_rate, 0) . '%):</span>
                    <span class="total-amount english-text">' . number_format($invoice_data['tax_amount'], 2) . ' ر.س</span>
                </div>';
    }
    
    // Grand Total
    $receipt_html .= '
                <div class="total-line grand-total">
                    <span class="total-label">المجموع الكلي:</span>
                    <span class="total-amount english-text">' . number_format($invoice_data['total_amount'], 2) . ' ر.س</span>
                </div>
            </div>';

    // Payment Information
    $receipt_html .= '
            <div class="payment-info">
                <div class="payment-method bold arabic-text">طريقة الدفع: ' . getPaymentMethodArabic($invoice_data['payment_method']) . '</div>';
    
    if ($invoice_data['payment_method'] === 'mixed' && !empty($invoice_data['payment_details'])) {
        foreach ($invoice_data['payment_details'] as $payment) {
            if ($payment['amount'] > 0) {
                $receipt_html .= '
                <div class="small">
                    ' . getPaymentMethodArabic($payment['method']) . ': ' . number_format($payment['amount'], 2) . ' ر.س
                </div>';
            }
        }
    }
    
    $receipt_html .= '
            </div>';

    // ZATCA QR Code Section (always show for Saudi compliance)
    $receipt_html .= '
        <div class="zatca-section">
            <div class="section-title center bold arabic-text">رمز الاستجابة السريعة ZATCA</div>';
    
    if (!empty($invoice_data['zatca_qr'])) {
        $receipt_html .= '
            <div class="qr-code">
                <img src="data:image/png;base64,' . $invoice_data['zatca_qr'] . '" alt="ZATCA QR Code" style="width: 80px; height: 80px; margin: 0 auto; display: block;">
            </div>';
    } else {
        // Generate QR using direct URL (works in browser)
        $zatca_qr_data = $invoice_data['zatca_qr_data'] ?? '';
        if (!empty($zatca_qr_data)) {
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($zatca_qr_data);
            $receipt_html .= '
            <div class="qr-code">
                <img src="' . $qr_url . '" alt="ZATCA QR Code" style="width: 80px; height: 80px; margin: 0 auto; display: block;" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">
                <div style="border: 2px solid #000; width: 80px; height: 80px; margin: 0 auto; display: none; align-items: center; justify-content: center; font-size: 8px; text-align: center;">
                    ZATCA<br>QR Code<br>Available
                </div>
            </div>';
        } else {
            $receipt_html .= '
            <div class="qr-placeholder center">
                <div style="border: 2px solid #000; width: 80px; height: 80px; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 8px; text-align: center;">
                    ZATCA<br>QR Code<br>Enabled
                </div>
            </div>';
        }
    }
    
    $receipt_html .= '
            <div class="zatca-text tiny english-text center">Scan for digital receipt</div>
            <div class="zatca-text tiny arabic-text center">امسح للحصول على الفاتورة الرقمية</div>
            <div class="zatca-text tiny arabic-text center">الرقم الضريبي: ' . ($business_settings['vat_number'] ?? '123456789012345') . '</div>
        </div>';

    // Footer
    $receipt_html .= '
            <div class="receipt-footer">
                <div class="thank-you bold arabic-text">شكراً لزيارتكم صالون MAI</div>
                <div class="thank-you bold english-text">Thank you for visiting MAI Salon</div>
                <div class="return-policy tiny arabic-text">
                    سياسة الاستبدال: يمكن استبدال المنتجات خلال 7 أيام من تاريخ الشراء مع إحضار الفاتورة
                </div>
                <div class="return-policy tiny english-text">
                    Return Policy: Products can be returned within 7 days with receipt
                </div>
            </div>

            <!-- Cut line indicator -->
            <div style="text-align: center; margin-top: 5mm; font-size: 8px;">
                ─────────── ✂ ───────────
            </div>
        </div>
    </div>

    <script>
        // Auto-print function
        function autoPrint() {
            window.print();
        }
        
        // Print when page loads (for direct printing)
        window.addEventListener("load", function() {
            if (new URLSearchParams(window.location.search).get("auto_print") === "1") {
                setTimeout(autoPrint, 500);
            }
        });
    </script>
</body>
</html>';

    return $receipt_html;
}

/**
 * Convert payment method to Arabic
 */
function getPaymentMethodArabic($method) {
    $methods = [
        'cash' => 'نقدي',
        'card' => 'بطاقة ائتمانية',
        'transfer' => 'تحويل بنكي',
        'mixed' => 'دفع مختلط'
    ];
    
    return $methods[$method] ?? $method;
}

/**
 * Generate printable receipt URL
 */
function generateReceiptUrl($invoice_id, $auto_print = false) {
    $url = "print_receipt.php?invoice_id=" . urlencode($invoice_id);
    if ($auto_print) {
        $url .= "&auto_print=1";
    }
    return $url;
}
?>
