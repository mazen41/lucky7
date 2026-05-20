<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if appointments table exists and has data
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM appointments");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Appointments count: " . $result['count'] . "\n";
    
    if ($result['count'] == 0) {
        echo "No appointments found. Creating sample data...\n";
        
        // Insert sample appointments
        $sample_appointments = [
            [
                'customer_name' => 'سارة أحمد',
                'customer_phone' => '0501234567',
                'customer_email' => 'sara@example.com',
                'service_id' => 1,
                'specialist_id' => 1,
                'start_at' => date('Y-m-d H:i:s', strtotime('today 10:00')),
                'end_at' => date('Y-m-d H:i:s', strtotime('today 11:00')),
                'status' => 'confirmed',
                'notes' => 'موعد تأكيدي',
                'source' => 'internal'
            ],
            [
                'customer_name' => 'فاطمة علي',
                'customer_phone' => '0507654321',
                'customer_email' => 'fatima@example.com',
                'service_id' => 2,
                'specialist_id' => 2,
                'start_at' => date('Y-m-d H:i:s', strtotime('today 14:00')),
                'end_at' => date('Y-m-d H:i:s', strtotime('today 15:00')),
                'status' => 'pending',
                'notes' => 'موعد معلق',
                'source' => 'public'
            ],
            [
                'customer_name' => 'مني سالم',
                'customer_phone' => '0509876543',
                'customer_email' => 'mona@example.com',
                'service_id' => 3,
                'specialist_id' => 1,
                'start_at' => date('Y-m-d H:i:s', strtotime('tomorrow 09:00')),
                'end_at' => date('Y-m-d H:i:s', strtotime('tomorrow 10:00')),
                'status' => 'confirmed',
                'notes' => 'موعد غداً',
                'source' => 'internal'
            ],
            [
                'customer_name' => 'هند محمد',
                'customer_phone' => '0502468135',
                'customer_email' => 'hind@example.com',
                'service_id' => 1,
                'specialist_id' => 2,
                'start_at' => date('Y-m-d H:i:s', strtotime('tomorrow 15:00')),
                'end_at' => date('Y-m-d H:i:s', strtotime('tomorrow 16:00')),
                'status' => 'done',
                'notes' => 'موعد منجز',
                'source' => 'internal'
            ],
            [
                'customer_name' => 'رنا عبدالله',
                'customer_phone' => '0508642097',
                'customer_email' => 'rana@example.com',
                'service_id' => 2,
                'specialist_id' => 1,
                'start_at' => date('Y-m-d H:i:s', strtotime('+2 days 11:00')),
                'end_at' => date('Y-m-d H:i:s', strtotime('+2 days 12:00')),
                'status' => 'no_show',
                'notes' => 'لم يحضر',
                'source' => 'public'
            ]
        ];
        
        $insert_query = "INSERT INTO appointments (customer_name, customer_phone, customer_email, service_id, specialist_id, start_at, end_at, status, notes, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $db->prepare($insert_query);
        
        foreach ($sample_appointments as $appointment) {
            $insert_stmt->execute([
                $appointment['customer_name'],
                $appointment['customer_phone'],
                $appointment['customer_email'],
                $appointment['service_id'],
                $appointment['specialist_id'],
                $appointment['start_at'],
                $appointment['end_at'],
                $appointment['status'],
                $appointment['notes'],
                $appointment['source']
            ]);
        }
        
        echo "Sample appointments created successfully!\n";
    }
    
    // Show current appointments
    $stmt = $db->query("SELECT * FROM appointments ORDER BY start_at ASC LIMIT 5");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent appointments:\n";
    foreach ($appointments as $apt) {
        echo "ID: {$apt['id']}, Customer: {$apt['customer_name']}, Start: {$apt['start_at']}, Status: {$apt['status']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
