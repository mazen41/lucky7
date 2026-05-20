<?php
// ajax_generate_qr.php

// Basic security and setup
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once 'users_functions.php'; 
require_once 'auth.php';
require_once 'includes/zatca_qr_generator.php'; // Ensure this path is correct

// Only allow logged-in admins to access this
checkAdminAuth();
header('Content-Type: application/json');

// Get POST data sent from the JavaScript function
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['total_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

try {
    // Fetch business settings needed for the QR code
    $database = new Database();
    $db = $database->getConnection();
    $settings_raw = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('pos_receipt_header', 'pos_business_tax_number')")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Prepare data for the QR code generator
    $zatca_data = [
        'seller_name'   => $settings_raw['pos_receipt_header'] ?? 'MAI Salon',
        'vat_number'    => $settings_raw['pos_business_tax_number'] ?? '000000000000000',
        'timestamp'     => date('Y-m-d\TH:i:s\Z', strtotime($input['created_at'])),
        'invoice_total' => (float)$input['total_amount'],
        'vat_amount'    => (float)$input['tax_amount']
    ];

    // Generate the QR code image URI using the reliable API method
    $qr_code_uri = ZatcaQRGenerator::createQrCodeUri($zatca_data);

    if (empty($qr_code_uri)) {
        throw new Exception('QR Code generation failed on the server.');
    }

    echo json_encode(['success' => true, 'qr_code_uri' => $qr_code_uri]);

} catch (Exception $e) {
    error_log("AJAX QR Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>