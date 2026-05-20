<?php
/**
 * Dashboard Cache Setup Script for MAI Salon
 * Sets up cache directory and creates cache warming script
 */

require_once '../config/database.php';
require_once '../app/Services/CacheService.php';

use App\Services\CacheService;

try {
    echo "📋 Setting up Dashboard Caching System...\n\n";
    
    // 1. Create cache directory
    echo "📁 Creating cache directory...\n";
    $cache_dir = __DIR__ . '/../storage/cache';
    
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
        echo "  Created: $cache_dir\n";
    } else {
        echo "  Cache directory already exists\n";
    }
    
    // 2. Test cache service
    echo "🔄 Testing cache service...\n";
    $cache = new CacheService($cache_dir);
    
    // Test writing to cache
    $test_data = [
        'test' => true,
        'timestamp' => time(),
        'message' => 'Cache test successful'
    ];
    
    $cache->put('test_key', $test_data);
    $retrieved_data = $cache->get('test_key');
    
    if ($retrieved_data && $retrieved_data['test'] === true) {
        echo "✅ Cache write and read test successful\n\n";
    } else {
        echo "❌ Cache test failed\n\n";
        throw new Exception("Cache test failed");
    }
    
    // 3. Create cache warming script
    echo "📝 Creating cache warming script...\n";
    $warming_script = __DIR__ . '/warm_dashboard_cache.php';
    
    $script_content = <<<'EOT'
<?php
/**
 * Dashboard Cache Warming Script for MAI Salon
 * Run this script via cron job every 5 minutes to keep dashboard data fresh
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/CacheService.php';

use App\Services\CacheService;

try {
    // Initialize services
    $database = new Database();
    $db = $database->getConnection();
    $cache = new CacheService();
    
    // Start time for performance tracking
    $start_time = microtime(true);
    $warmed_keys = [];
    
    // 1. Cache today's sales statistics
    $sales_query = "
        SELECT 
            COUNT(*) as invoice_count,
            SUM(total_amount) as total_sales,
            AVG(total_amount) as average_sale,
            SUM(tax_amount) as total_tax
        FROM invoices 
        WHERE DATE(created_at) = CURDATE() AND status = 'paid'
    ";
    $sales_stmt = $db->prepare($sales_query);
    $sales_stmt->execute();
    $sales_data = $sales_stmt->fetch(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_today_sales', $sales_data);
    $warmed_keys[] = 'dashboard_today_sales';
    
    // 2. Cache payment method breakdown
    $payment_query = "
        SELECT 
            p.method,
            COUNT(*) as count,
            SUM(p.amount) as total
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        WHERE DATE(p.created_at) = CURDATE() AND i.status = 'paid'
        GROUP BY p.method
    ";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->execute();
    $payment_data = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_payment_methods', $payment_data);
    $warmed_keys[] = 'dashboard_payment_methods';
    
    // 3. Cache upcoming appointments (next 4 hours)
    $appointments_query = "
        SELECT 
            a.id,
            a.customer_id,
            c.name as customer_name,
            c.phone as customer_phone,
            s.title_ar as service_name,
            t.name_ar as specialist_name,
            a.start_at,
            a.end_at,
            a.status
        FROM appointments a
        LEFT JOIN customers c ON a.customer_id = c.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN team_members t ON a.specialist_id = t.id
        WHERE a.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 HOUR)
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.start_at ASC
        LIMIT 10
    ";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->execute();
    $appointments_data = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_upcoming_appointments', $appointments_data);
    $warmed_keys[] = 'dashboard_upcoming_appointments';
    
    // 4. Cache low stock inventory alerts
    $inventory_query = "
        SELECT 
            p.id,
            p.name_ar,
            p.sku,
            p.stock_quantity,
            p.min_stock_level,
            pc.name_ar as category_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.stock_quantity <= p.min_stock_level
        AND p.is_active = 1
        ORDER BY (p.stock_quantity / p.min_stock_level) ASC
        LIMIT 10
    ";
    $inventory_stmt = $db->prepare($inventory_query);
    $inventory_stmt->execute();
    $inventory_data = $inventory_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_low_stock', $inventory_data);
    $warmed_keys[] = 'dashboard_low_stock';
    
    // 5. Cache open shifts
    $shifts_query = "
        SELECT 
            s.id,
            s.user_id,
            u.name as user_name,
            s.opened_at,
            s.opening_cash,
            TIMESTAMPDIFF(HOUR, s.opened_at, NOW()) as hours_open
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'opened'
    ";
    $shifts_stmt = $db->prepare($shifts_query);
    $shifts_stmt->execute();
    $shifts_data = $shifts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_open_shifts', $shifts_data);
    $warmed_keys[] = 'dashboard_open_shifts';
    
    // 6. Cache recent invoices
    $invoices_query = "
        SELECT 
            i.id,
            i.invoice_number,
            i.customer_name,
            i.total_amount,
            i.created_at,
            u.name as cashier_name
        FROM invoices i
        LEFT JOIN users u ON i.cashier_id = u.id
        WHERE i.status = 'paid'
        ORDER BY i.created_at DESC
        LIMIT 5
    ";
    $invoices_stmt = $db->prepare($invoices_query);
    $invoices_stmt->execute();
    $invoices_data = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cache->put('dashboard_recent_invoices', $invoices_data);
    $warmed_keys[] = 'dashboard_recent_invoices';
    
    // Calculate execution time
    $execution_time = microtime(true) - $start_time;
    
    // Log cache warming results
    $log_message = date('Y-m-d H:i:s') . " - Cache warming completed in " . round($execution_time, 2) . " seconds. Warmed keys: " . implode(', ', $warmed_keys) . "\n";
    file_put_contents(__DIR__ . '/../storage/logs/cache_warming.log', $log_message, FILE_APPEND);
    
    echo "Cache warming completed successfully.\n";
    
} catch (Exception $e) {
    $error_message = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../storage/logs/cache_warming.log', $error_message, FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
}
EOT;
    
    file_put_contents($warming_script, $script_content);
    echo "  Created: $warming_script\n\n";
    
    // 4. Create logs directory
    echo "📁 Creating logs directory...\n";
    $logs_dir = __DIR__ . '/../storage/logs';
    
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
        echo "  Created: $logs_dir\n";
    } else {
        echo "  Logs directory already exists\n";
    }
    
    // 5. Create cron job instructions
    echo "⏰ Creating cron job instructions...\n";
    $cron_instructions = __DIR__ . '/../CRON_SETUP.md';
    
    $cron_content = <<<'EOT'
# MAI Salon Cron Jobs Setup

This document provides instructions for setting up the required cron jobs for MAI Salon CRM.

## Dashboard Cache Warming

To keep the dashboard data fresh and improve performance, set up a cron job to run every 5 minutes:

```bash
# Run every 5 minutes
*/5 * * * * php /path/to/mai_salon/admin/warm_dashboard_cache.php >> /path/to/mai_salon/storage/logs/cache_warming.log 2>&1
```

## Loyalty Points Decay

To process loyalty points decay (downgrade inactive customers), set up a daily cron job:

```bash
# Run daily at midnight
0 0 * * * php /path/to/mai_salon/admin/loyalty_cron.php >> /path/to/mai_salon/storage/logs/loyalty_cron.log 2>&1
```

## Database Backup

To create daily backups of the database, set up a daily cron job:

```bash
# Run daily at 2 AM
0 2 * * * php /path/to/mai_salon/admin/backup_database.php >> /path/to/mai_salon/storage/logs/backup.log 2>&1
```

## Setting Up Cron Jobs in cPanel

If you're using cPanel:

1. Log in to your cPanel account
2. Find the "Cron Jobs" icon in the "Advanced" section
3. Select the appropriate time interval from the dropdown menu
4. Enter the command as shown above (adjust the paths as needed)
5. Click "Add New Cron Job"

## Setting Up Cron Jobs in Windows

If you're using Windows Task Scheduler:

1. Open Task Scheduler
2. Click "Create Basic Task"
3. Enter a name and description
4. Set the trigger to "Daily" or as needed
5. Set the action to "Start a program"
6. Enter the path to PHP executable (e.g., `C:\xampp\php\php.exe`)
7. Add arguments (e.g., `C:\xampp\htdocs\mai_salon\admin\warm_dashboard_cache.php`)
8. Complete the wizard

## Verifying Cron Jobs

To verify that your cron jobs are running correctly, check the log files in the `storage/logs` directory.
EOT;
    
    file_put_contents($cron_instructions, $cron_content);
    echo "  Created: $cron_instructions\n\n";
    
    // 6. Warm the cache for the first time
    echo "🔄 Warming cache for the first time...\n";
    include $warming_script;
    echo "✅ Initial cache warming completed\n\n";
    
    echo "🎉 Dashboard Caching System setup completed successfully!\n";
    echo "Please set up the cron job as described in CRON_SETUP.md to keep the cache fresh.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>
