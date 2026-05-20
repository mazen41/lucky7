<?php
/**
 * Add Sample Customers with proper customer codes
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "👥 Adding sample customers to CRM...\n\n";
    
    // Check if customers already exist
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM customers");
    $count_stmt->execute();
    $customer_count = $count_stmt->fetchColumn();
    
    if ($customer_count > 0) {
        echo "✓ Found $customer_count existing customers\n";
        echo "🔗 CRM is ready at: /admin/clients.php\n";
        exit;
    }
    
    // Sample customers with beautiful Arabic names
    $sample_customers = [
        ['فاطمة أحمد السالم', 'fatima.salem@email.com', '0501234567', '1990-05-15', 'female', 'الرياض', 'normal'],
        ['نورا محمد العتيبي', 'nora.otaibi@email.com', '0507654321', '1985-08-22', 'female', 'جدة', 'dry'],
        ['سارة عبدالله القحطاني', 'sara.qahtani@email.com', '0512345678', '1992-12-10', 'female', 'الدمام', 'oily'],
        ['هند سعد الغامدي', 'hind.ghamdi@email.com', '0509876543', '1988-03-18', 'female', 'الرياض', 'combination'],
        ['ريم خالد الشهري', 'reem.shehri@email.com', '0503456789', '1995-07-25', 'female', 'مكة', 'sensitive'],
        ['أمل عبدالرحمن النعيمي', 'amal.naimi@email.com', '0508765432', '1987-11-08', 'female', 'المدينة', 'normal'],
        ['لينا فهد الدوسري', 'lina.dosari@email.com', '0504567890', '1993-04-12', 'female', 'الخبر', 'dry'],
        ['منى طارق الحربي', 'mona.harbi@email.com', '0506789012', '1991-09-30', 'female', 'الطائف', 'oily']
    ];
    
    // Insert customers one by one with proper customer codes
    foreach ($sample_customers as $index => $customer) {
        $customer_code = 'CUS' . date('Y') . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
        
        $stmt = $db->prepare("
            INSERT INTO customers (
                customer_code, name, email, phone, date_of_birth, 
                gender, city, skin_type, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)
        ");
        
        try {
            $stmt->execute([
                $customer_code,
                $customer[0], // name
                $customer[1], // email
                $customer[2], // phone
                $customer[3], // date_of_birth
                $customer[4], // gender
                $customer[5], // city
                $customer[6]  // skin_type
            ]);
            
            echo "✅ Added: " . $customer[0] . " ($customer_code)\n";
        } catch (Exception $e) {
            echo "⚠️ Warning adding " . $customer[0] . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Update customer count
    $count_stmt->execute();
    $final_count = $count_stmt->fetchColumn();
    
    echo "\n🎉 Sample customers added successfully!\n";
    echo "📊 Total customers: $final_count\n";
    echo "🔗 Access CRM at: /admin/clients.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
