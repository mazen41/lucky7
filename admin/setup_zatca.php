<?php
/**
 * ZATCA Setup Script for MAI Salon
 * Sets up database schema and storage directories for ZATCA e-invoicing
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "📋 Setting up ZATCA e-invoicing system...\n\n";
    
    // 1. Execute SQL schema update
    echo "🔄 Updating database schema...\n";
    $sql_file = file_get_contents(__DIR__ . '/../database/zatca_schema_update.sql');
    $db->exec($sql_file);
    echo "✅ Database schema updated successfully\n\n";
    
    // 2. Create storage directories for ZATCA XML files
    echo "📁 Creating storage directories...\n";
    $base_dir = __DIR__ . '/../storage/invoices';
    
    if (!is_dir($base_dir)) {
        mkdir($base_dir, 0755, true);
        echo "  Created: $base_dir\n";
    }
    
    // Create year/month directories for current and next month
    $current_year = date('Y');
    $current_month = date('m');
    $next_month = date('m', strtotime('+1 month'));
    
    $dirs_to_create = [
        "$base_dir/$current_year/$current_month",
        "$base_dir/$current_year/$next_month"
    ];
    
    foreach ($dirs_to_create as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "  Created: $dir\n";
        }
    }
    
    echo "✅ Storage directories created successfully\n\n";
    
    // 3. Check for required libraries
    echo "📚 Checking required libraries...\n";
    $required_extensions = ['dom', 'xml', 'gd', 'mbstring'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        echo "✅ All required PHP extensions are available\n\n";
    } else {
        echo "⚠️ Missing PHP extensions: " . implode(', ', $missing_extensions) . "\n";
        echo "   Please enable these extensions in your PHP configuration\n\n";
    }
    
    // 4. Update site settings
    echo "⚙️ Configuring ZATCA settings...\n";
    
    // Check if settings already exist
    $settings_query = "SELECT COUNT(*) FROM site_settings WHERE setting_key = 'pos_zatca_enabled'";
    $settings_count = $db->query($settings_query)->fetchColumn();
    
    if ($settings_count == 0) {
        $settings_insert = "
            INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
            ('pos_zatca_enabled', '1', 'text'),
            ('pos_business_name', 'صالون MAI للجمال', 'text'),
            ('pos_business_tax_number', '123456789012345', 'text')
        ";
        $db->exec($settings_insert);
        echo "  Added default ZATCA settings\n";
    } else {
        echo "  ZATCA settings already exist\n";
    }
    
    echo "✅ ZATCA settings configured successfully\n\n";
    
    // 5. Create test QR code
    echo "🔄 Testing QR code generation...\n";
    
    // Include ZATCA service
    require_once __DIR__ . '/../app/Services/ZatcaService.php';
    
    // Test data
    $test_invoice = [
        'seller_name' => 'صالون MAI للجمال',
        'vat_number' => '123456789012345',
        'timestamp' => date('Y-m-d\TH:i:s\Z'),
        'invoice_total' => 115.00,
        'vat_amount' => 15.00
    ];
    
    try {
        $qr_data = \App\Services\ZatcaService::generateQRCodeData($test_invoice);
        echo "✅ QR code generation successful\n";
        echo "  Sample QR data: " . substr($qr_data, 0, 50) . "...\n\n";
    } catch (Exception $e) {
        echo "❌ QR code generation failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 ZATCA e-invoicing system setup completed successfully!\n";
    echo "You can now enable ZATCA QR codes in your POS system settings.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>
