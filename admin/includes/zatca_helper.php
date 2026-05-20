<?php
/**
 * ZATCA Helper Functions for MAI Salon POS
 * Integrates ZATCA QR code generation with POS receipts
 */

require_once __DIR__ . '/../../app/Services/ZatcaService.php';

use App\Services\ZatcaService;

/**
 * Generate ZATCA QR code for invoice
 * 
 * @param array $invoice Invoice data
 * @return string HTML for QR code display
 */
function generateZatcaQRCode($invoice) {
    // Get settings
    global $db;
    $settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('pos_zatca_enabled', 'pos_business_name', 'pos_business_tax_number')";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Check if ZATCA is enabled
    if (empty($settings['pos_zatca_enabled']) || $settings['pos_zatca_enabled'] != '1') {
        return '';
    }
    
    // Prepare invoice data for ZATCA
    $zatca_invoice = [
        'seller_name' => $settings['pos_business_name'] ?? 'MAI Salon',
        'vat_number' => $settings['pos_business_tax_number'] ?? '123456789012345',
        'timestamp' => date('Y-m-d\TH:i:s\Z', strtotime($invoice['created_at'] ?? 'now')),
        'invoice_total' => $invoice['total_amount'] ?? 0,
        'vat_amount' => $invoice['tax_amount'] ?? 0
    ];
    
    // Generate QR code data
    $qr_data = ZatcaService::generateQRCodeData($zatca_invoice);
    
    // Generate QR code HTML
    return ZatcaService::generateQRCodeHTML($qr_data);
}

/**
 * Generate and save ZATCA XML for invoice
 * 
 * @param array $invoice Invoice data
 * @param int $invoice_id Invoice ID
 * @return string Path to saved XML file
 */
function generateAndSaveZatcaXML($invoice, $invoice_id) {
    // Get settings
    global $db;
    $settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('pos_zatca_enabled', 'pos_business_name', 'pos_business_tax_number')";
    $settings_stmt = $db->prepare($settings_query);
    $settings_stmt->execute();
    $settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Check if ZATCA is enabled
    if (empty($settings['pos_zatca_enabled']) || $settings['pos_zatca_enabled'] != '1') {
        return '';
    }
    
    // Prepare invoice data for ZATCA
    $zatca_invoice = [
        'invoice_number' => $invoice['invoice_number'] ?? 'INV-' . $invoice_id,
        'created_at' => $invoice['created_at'] ?? date('Y-m-d H:i:s'),
        'seller_name' => $settings['pos_business_name'] ?? 'MAI Salon',
        'vat_number' => $settings['pos_business_tax_number'] ?? '123456789012345',
        'customer_name' => $invoice['customer_name'] ?? '',
        'customer_phone' => $invoice['customer_phone'] ?? '',
        'items' => $invoice['items'] ?? [],
        'subtotal' => $invoice['subtotal'] ?? 0,
        'tax_amount' => $invoice['tax_amount'] ?? 0,
        'total_amount' => $invoice['total_amount'] ?? 0
    ];
    
    // Generate XML
    $xml = ZatcaService::generateInvoiceXML($zatca_invoice);
    
    // Create directory if not exists
    $dir = __DIR__ . '/../../storage/invoices/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Save XML file
    $file_path = $dir . '/' . $invoice_id . '.xml';
    file_put_contents($file_path, $xml);
    
    // Update invoice with XML path
    $update_query = "UPDATE invoices SET zatca_xml_path = ? WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([str_replace(__DIR__ . '/../../', '', $file_path), $invoice_id]);
    
    return $file_path;
}
