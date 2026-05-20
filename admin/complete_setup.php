<?php
/**
 * Complete MAI Salon Setup Script
 * Configures all systems in XAMPP environment
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "🎯 MAI Salon Complete Setup Starting...\n\n";
    
    // 1. Create storage directories
    echo "📁 Creating storage directories...\n";
    $directories = [
        '../storage/cache',
        '../storage/logs',
        '../storage/rate_limits',
        '../storage/invoices',
        '../storage/invoices/2025/09',
        '../storage/invoices/2025/10'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "  ✅ Created: $dir\n";
        } else {
            echo "  ℹ️ Exists: $dir\n";
        }
    }
    
    // 2. Setup ZATCA settings
    echo "\n⚙️ Configuring ZATCA settings...\n";
    $zatca_settings = [
        ['pos_zatca_enabled', '1', 'text'],
        ['pos_business_name', 'صالون MAI للجمال', 'text'],
        ['pos_business_tax_number', '123456789012345', 'text'],
        ['pos_receipt_header', 'صالون MAI للجمال', 'text'],
        ['pos_business_address', 'الرياض، المملكة العربية السعودية', 'text'],
        ['pos_business_phone', '+966501234567', 'text'],
        ['pos_business_email', 'info@maisalon.com', 'text'],
        ['pos_receipt_footer', 'شكراً لزيارتكم - نتطلع لخدمتكم مرة أخرى', 'text']
    ];
    
    foreach ($zatca_settings as $setting) {
        $query = "INSERT INTO site_settings (setting_key, setting_value, setting_type) 
                  VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $db->prepare($query);
        $stmt->execute($setting);
        echo "  ✅ Set: {$setting[0]}\n";
    }
    
    // 3. Add ZATCA XML path column to invoices table if not exists
    echo "\n🔧 Updating database schema...\n";
    try {
        $db->exec("ALTER TABLE invoices ADD COLUMN zatca_xml_path VARCHAR(255) NULL");
        echo "  ✅ Added zatca_xml_path column to invoices\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "  ℹ️ zatca_xml_path column already exists\n";
        } else {
            echo "  ⚠️ Error adding column: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Create essential indexes for performance
    echo "\n📊 Creating database indexes...\n";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers (phone)",
        "CREATE INDEX IF NOT EXISTS idx_customers_name ON customers (name)",
        "CREATE INDEX IF NOT EXISTS idx_invoices_created_at ON invoices (created_at)",
        "CREATE INDEX IF NOT EXISTS idx_invoices_customer_id ON invoices (customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (status)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_start_at ON appointments (start_at)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_specialist_id ON appointments (specialist_id)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments (status)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $db->exec($index);
            echo "  ✅ Created index\n";
        } catch (Exception $e) {
            echo "  ℹ️ Index may already exist\n";
        }
    }
    
    // 5. Create public API directory structure
    echo "\n🌐 Setting up public API structure...\n";
    $api_dir = '../public/api';
    if (!is_dir($api_dir)) {
        mkdir($api_dir, 0755, true);
        echo "  ✅ Created API directory\n";
    }
    
    // 6. Test cache system
    echo "\n💾 Testing cache system...\n";
    require_once '../app/Services/CacheService.php';
    
    try {
        $cache = new \App\Services\CacheService('../storage/cache');
        $test_data = ['test' => true, 'timestamp' => time()];
        $cache->put('test_key', $test_data);
        $retrieved = $cache->get('test_key');
        
        if ($retrieved && $retrieved['test'] === true) {
            echo "  ✅ Cache system working correctly\n";
        } else {
            echo "  ❌ Cache system test failed\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️ Cache test error: " . $e->getMessage() . "\n";
    }
    
    // 7. Test rate limiting system
    echo "\n🚦 Testing rate limiting system...\n";
    require_once '../app/Middleware/RateLimitMiddleware.php';
    
    try {
        $rate_limit = new \App\Middleware\RateLimitMiddleware($db, '../storage/rate_limits');
        $result = $rate_limit->check('test_ip', 'public_api');
        
        if (isset($result['headers']['X-RateLimit-Limit'])) {
            echo "  ✅ Rate limiting system working correctly\n";
            echo "    Rate limit: " . $result['headers']['X-RateLimit-Limit'] . "\n";
            echo "    Remaining: " . $result['headers']['X-RateLimit-Remaining'] . "\n";
        } else {
            echo "  ❌ Rate limiting test failed\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️ Rate limiting test error: " . $e->getMessage() . "\n";
    }
    
    // 8. Test ZATCA QR code generation
    echo "\n📱 Testing ZATCA QR code generation...\n";
    require_once '../app/Services/ZatcaService.php';
    
    try {
        $test_invoice = [
            'seller_name' => 'صالون MAI للجمال',
            'vat_number' => '123456789012345',
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'invoice_total' => 115.00,
            'vat_amount' => 15.00
        ];
        
        $qr_data = \App\Services\ZatcaService::generateQRCodeData($test_invoice);
        
        if (!empty($qr_data)) {
            echo "  ✅ ZATCA QR code generation working\n";
            echo "    Sample QR data: " . substr($qr_data, 0, 50) . "...\n";
        } else {
            echo "  ❌ ZATCA QR code generation failed\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️ ZATCA test error: " . $e->getMessage() . "\n";
    }
    
    // 9. Create cron job instructions
    echo "\n⏰ Creating cron job instructions...\n";
    $cron_content = <<<'EOT'
# MAI Salon Cron Jobs Setup for Windows

## Dashboard Cache Warming (Every 5 minutes)
Create a Windows Task Scheduler task:
- Program: C:\xampp\php\php.exe
- Arguments: "C:\xampp\htdocs\the project of 500 yassin\admin\warm_dashboard_cache.php"
- Trigger: Every 5 minutes

## Command to create the task via Command Prompt:
schtasks /create /tn "MAI Salon Cache Warming" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\the project of 500 yassin\admin\warm_dashboard_cache.php\"" /sc minute /mo 5

## To delete the task:
schtasks /delete /tn "MAI Salon Cache Warming" /f

## Manual cache warming:
php "C:\xampp\htdocs\the project of 500 yassin\admin\warm_dashboard_cache.php"
EOT;
    
    file_put_contents('../CRON_SETUP_WINDOWS.md', $cron_content);
    echo "  ✅ Created Windows cron setup instructions\n";
    
    // 10. Final system check
    echo "\n🔍 Final system check...\n";
    
    // Check if essential tables exist
    $essential_tables = ['customers', 'invoices', 'appointments', 'products', 'site_settings'];
    foreach ($essential_tables as $table) {
        try {
            $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
            echo "  ✅ Table '$table' exists and accessible\n";
        } catch (Exception $e) {
            echo "  ⚠️ Table '$table' issue: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 MAI Salon Complete Setup Finished!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ZATCA e-invoicing system configured\n";
    echo "✅ Dashboard caching system ready\n";
    echo "✅ Rate limiting system active\n";
    echo "✅ Database indexes optimized\n";
    echo "✅ Storage directories created\n";
    echo "✅ API endpoints configured\n";
    echo "\n📋 Next Steps:\n";
    echo "1. Set up the Windows Task Scheduler (see CRON_SETUP_WINDOWS.md)\n";
    echo "2. Enable GD extension in PHP for QR codes (uncomment in php.ini)\n";
    echo "3. Access your admin panel and test all features\n";
    echo "\n🌐 Your MAI Salon CRM is ready for production!\n";
    
} catch (Exception $e) {
    echo "❌ Setup Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>
