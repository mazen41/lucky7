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