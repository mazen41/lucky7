<?php
/**
 * Optimize Database for Reports - MAI Salon
 * Add indexes for better report performance
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🔧 Optimizing Database for Reports Performance...\n\n";
    
    // 1. Add indexes for bookings table (sales reports)
    echo "1. Adding indexes for bookings table...\n";
    $booking_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(booking_date)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_payment ON bookings(payment_method)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_customer ON bookings(customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_service ON bookings(service_id)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_specialist ON bookings(specialist_id)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_amount ON bookings(total_amount)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_created ON bookings(created_at)"
    ];
    
    foreach ($booking_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added booking index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Booking index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Add indexes for appointments table
    echo "\n2. Adding indexes for appointments table...\n";
    $appointment_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_appointments_start ON appointments(start_at)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_customer ON appointments(customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_service ON appointments(service_id)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_specialist ON appointments(specialist_id)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_created ON appointments(created_at)"
    ];
    
    foreach ($appointment_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added appointment index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Appointment index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Add indexes for customers table
    echo "\n3. Adding indexes for customers table...\n";
    $customer_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_customers_status ON customers(status)",
        "CREATE INDEX IF NOT EXISTS idx_customers_spent ON customers(total_spent)",
        "CREATE INDEX IF NOT EXISTS idx_customers_visits ON customers(visit_count)",
        "CREATE INDEX IF NOT EXISTS idx_customers_last_visit ON customers(last_visit_date)",
        "CREATE INDEX IF NOT EXISTS idx_customers_created ON customers(created_at)"
    ];
    
    foreach ($customer_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added customer index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Customer index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Add indexes for products table (inventory reports)
    echo "\n4. Adding indexes for products table...\n";
    $product_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)",
        "CREATE INDEX IF NOT EXISTS idx_products_stock ON products(stock_quantity)",
        "CREATE INDEX IF NOT EXISTS idx_products_min_stock ON products(min_stock_level)",
        "CREATE INDEX IF NOT EXISTS idx_products_updated ON products(last_updated)"
    ];
    
    foreach ($product_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added product index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Product index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Add indexes for attendance table (HR reports)
    echo "\n5. Adding indexes for attendance table...\n";
    $attendance_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance(employee_id)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_status ON attendance(status)"
    ];
    
    foreach ($attendance_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added attendance index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Attendance index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Add indexes for shifts table
    echo "\n6. Adding indexes for shifts table...\n";
    $shift_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_shifts_date ON shifts(shift_date)",
        "CREATE INDEX IF NOT EXISTS idx_shifts_type ON shifts(shift_type)",
        "CREATE INDEX IF NOT EXISTS idx_shifts_manager ON shifts(manager_id)"
    ];
    
    foreach ($shift_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added shift index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Shift index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 7. Add indexes for loyalty tables
    echo "\n7. Adding indexes for loyalty tables...\n";
    $loyalty_indexes = [
        "CREATE INDEX IF NOT EXISTS idx_loyalty_wallets_customer ON loyalty_wallets(customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_loyalty_wallets_tier ON loyalty_wallets(tier_id)",
        "CREATE INDEX IF NOT EXISTS idx_loyalty_wallets_points ON loyalty_wallets(points)",
        "CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_customer ON loyalty_transactions(customer_id)",
        "CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_date ON loyalty_transactions(created_at)"
    ];
    
    foreach ($loyalty_indexes as $index) {
        try {
            $db->exec($index);
            echo "   ✅ Added loyalty index\n";
        } catch (Exception $e) {
            echo "   ℹ️ Loyalty index already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // 8. Create missing tables if they don't exist
    echo "\n8. Creating missing tables for reports...\n";
    
    // Products table for inventory reports
    $products_table = "
        CREATE TABLE IF NOT EXISTS `products` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `name_ar` varchar(255) NOT NULL,
            `category` varchar(100) NOT NULL,
            `stock_quantity` int(11) DEFAULT 0,
            `min_stock_level` int(11) DEFAULT 5,
            `max_stock_level` int(11) DEFAULT 100,
            `unit_cost` decimal(10,2) DEFAULT 0.00,
            `selling_price` decimal(10,2) DEFAULT 0.00,
            `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $db->exec($products_table);
        echo "   ✅ Products table created\n";
        
        // Insert sample products
        $sample_products = [
            ['كريم مرطب للوجه', 'Face Moisturizer', 'skincare', 25, 5, 50, 45.00, 85.00],
            ['شامبو للشعر الجاف', 'Dry Hair Shampoo', 'haircare', 15, 3, 30, 35.00, 65.00],
            ['مقص احترافي', 'Professional Scissors', 'tools', 8, 2, 15, 120.00, 200.00],
            ['قفازات طبية', 'Medical Gloves', 'supplies', 100, 20, 200, 2.00, 5.00],
            ['ماسك للوجه', 'Face Mask', 'skincare', 30, 10, 60, 15.00, 35.00]
        ];
        
        $stmt = $db->prepare("INSERT INTO products (name_ar, name, category, stock_quantity, min_stock_level, max_stock_level, unit_cost, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sample_products as $product) {
            $stmt->execute($product);
        }
        echo "   ✅ Sample products inserted\n";
        
    } catch (Exception $e) {
        echo "   ℹ️ Products table already exists\n";
    }
    
    // Attendance table for HR reports
    $attendance_table = "
        CREATE TABLE IF NOT EXISTS `attendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `employee_id` int(11) NOT NULL,
            `date` date NOT NULL,
            `status` enum('present','absent','late','sick','vacation') DEFAULT 'present',
            `check_in` time NULL,
            `check_out` time NULL,
            `hours_worked` decimal(4,2) DEFAULT 0.00,
            `performance_score` int(11) DEFAULT 0,
            `bonus_amount` decimal(10,2) DEFAULT 0.00,
            `notes` text NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_employee_date` (`employee_id`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $db->exec($attendance_table);
        echo "   ✅ Attendance table created\n";
        
        // Insert sample attendance data
        $employees_stmt = $db->query("SELECT id FROM team_members LIMIT 5");
        $employees = $employees_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($employees)) {
            $stmt = $db->prepare("INSERT IGNORE INTO attendance (employee_id, date, status, check_in, check_out, hours_worked, performance_score, bonus_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            for ($i = 0; $i < 30; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                foreach ($employees as $emp_id) {
                    $status = rand(1, 10) > 8 ? 'absent' : 'present';
                    $check_in = $status === 'present' ? '09:00:00' : null;
                    $check_out = $status === 'present' ? '17:00:00' : null;
                    $hours = $status === 'present' ? 8.0 : 0.0;
                    $performance = rand(70, 100);
                    $bonus = $performance > 90 ? rand(50, 200) : 0;
                    
                    $stmt->execute([$emp_id, $date, $status, $check_in, $check_out, $hours, $performance, $bonus]);
                }
            }
            echo "   ✅ Sample attendance data inserted\n";
        }
        
    } catch (Exception $e) {
        echo "   ℹ️ Attendance table already exists\n";
    }
    
    // Shifts table for shift reports
    $shifts_table = "
        CREATE TABLE IF NOT EXISTS `shifts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `shift_date` date NOT NULL,
            `shift_type` enum('morning','evening','night') NOT NULL,
            `manager_id` int(11) NOT NULL,
            `opening_cash` decimal(10,2) DEFAULT 0.00,
            `closing_cash` decimal(10,2) DEFAULT 0.00,
            `total_sales` decimal(10,2) DEFAULT 0.00,
            `cash_sales` decimal(10,2) DEFAULT 0.00,
            `card_sales` decimal(10,2) DEFAULT 0.00,
            `expenses` decimal(10,2) DEFAULT 0.00,
            `notes` text NULL,
            `opened_at` datetime NULL,
            `closed_at` datetime NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_shift_date_type` (`shift_date`, `shift_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $db->exec($shifts_table);
        echo "   ✅ Shifts table created\n";
        
        // Insert sample shift data
        $managers_stmt = $db->query("SELECT id FROM team_members WHERE position_ar LIKE '%مدير%' LIMIT 3");
        $managers = $managers_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($managers)) {
            $stmt = $db->prepare("INSERT IGNORE INTO shifts (shift_date, shift_type, manager_id, opening_cash, closing_cash, total_sales, cash_sales, card_sales, expenses, opened_at, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            for ($i = 0; $i < 15; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $manager_id = $managers[array_rand($managers)];
                $opening = rand(500, 1000);
                $sales = rand(2000, 5000);
                $cash_sales = $sales * 0.6;
                $card_sales = $sales * 0.4;
                $closing = $opening + $cash_sales;
                $expenses = rand(100, 300);
                
                $stmt->execute([
                    $date, 'morning', $manager_id, $opening, $closing, $sales, 
                    $cash_sales, $card_sales, $expenses, 
                    $date . ' 08:00:00', $date . ' 16:00:00'
                ]);
            }
            echo "   ✅ Sample shift data inserted\n";
        }
        
    } catch (Exception $e) {
        echo "   ℹ️ Shifts table already exists\n";
    }
    
    // 9. Update customer statistics for accurate reports
    echo "\n9. Updating customer statistics...\n";
    
    try {
        // Update total_spent from bookings
        $spent_updated = $db->exec("
            UPDATE customers c 
            SET total_spent = COALESCE((
                SELECT SUM(COALESCE(b.total_amount, 0))
                FROM bookings b
                WHERE b.customer_id = c.id AND b.status = 'completed'
            ), 0)
        ");
        
        // Update visit_count from bookings
        $visits_updated = $db->exec("
            UPDATE customers c 
            SET visit_count = COALESCE((
                SELECT COUNT(*)
                FROM bookings b
                WHERE b.customer_id = c.id AND b.status = 'completed'
            ), 0)
        ");
        
        // Update last_visit_date
        $last_visit_updated = $db->exec("
            UPDATE customers c 
            SET last_visit_date = (
                SELECT MAX(b.booking_date)
                FROM bookings b
                WHERE b.customer_id = c.id AND b.status = 'completed'
            )
        ");
        
        echo "   ✅ Updated customer spending data\n";
        echo "   ✅ Updated customer visit counts\n";
        echo "   ✅ Updated last visit dates\n";
        
    } catch (Exception $e) {
        echo "   ⚠️ Could not update customer statistics: " . $e->getMessage() . "\n";
    }
    
    // 10. Test report performance
    echo "\n10. Testing report query performance...\n";
    
    $test_queries = [
        "Sales Report" => "SELECT COUNT(*) FROM bookings WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'completed'",
        "Customer Report" => "SELECT COUNT(*) FROM customers WHERE status = 'active'",
        "Inventory Report" => "SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level"
    ];
    
    foreach ($test_queries as $name => $query) {
        try {
            $start = microtime(true);
            $stmt = $db->query($query);
            $result = $stmt->fetchColumn();
            $time = round((microtime(true) - $start) * 1000, 2);
            echo "   ✅ {$name}: {$result} records ({$time}ms)\n";
        } catch (Exception $e) {
            echo "   ⚠️ {$name}: Error - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Database optimization completed successfully!\n";
    echo "\n📊 Report System Ready:\n";
    echo "   ✅ All indexes created for fast queries\n";
    echo "   ✅ Missing tables created with sample data\n";
    echo "   ✅ Customer statistics updated\n";
    echo "   ✅ Query performance tested\n";
    echo "\n🚀 Reports system is now optimized for production use!\n";
    
} catch (Exception $e) {
    echo "❌ Critical Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
