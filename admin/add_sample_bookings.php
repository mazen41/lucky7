<?php
/**
 * Add Sample Bookings Data - MAI Salon
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "📝 Adding sample bookings data...\n\n";
    
    // Insert sample bookings without service_id to avoid foreign key constraint
    $bookings_data = [
        ['سارة أحمد الزهراني', '0501234567', 'sara@example.com', 'pending', 'قص وتصفيف الشعر'],
        ['فاطمة محمد العتيبي', '0509876543', 'fatima@example.com', 'confirmed', 'عناية بالبشرة'],
        ['نورا عبدالله القحطاني', '0551234567', 'nora@example.com', 'pending', 'مانيكير وباديكير'],
        ['مريم خالد الشهري', '0502468135', 'mariam@example.com', 'confirmed', 'صبغة الشعر'],
        ['ريم طارق الحربي', '0508642097', 'reem@example.com', 'completed', 'تنظيف البشرة'],
        ['لينا سعد المطيري', '0556789012', 'lina@example.com', 'pending', 'قص شعر'],
        ['هند علي الغامدي', '0503456789', 'hind@example.com', 'confirmed', 'فيشل للوجه'],
        ['دانا محمد الدوسري', '0507890123', 'dana@example.com', 'completed', 'عناية بالأظافر']
    ];
    
    $insert_query = "INSERT INTO bookings (customer_name, customer_phone, customer_email, status, notes, booking_date, booking_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($insert_query);
    
    $dates = [
        date('Y-m-d'), // Today
        date('Y-m-d'), // Today
        date('Y-m-d', strtotime('+1 day')), // Tomorrow
        date('Y-m-d', strtotime('+2 days')), // Day after tomorrow
        date('Y-m-d', strtotime('-1 day')), // Yesterday
        date('Y-m-d', strtotime('+3 days')), // 3 days from now
        date('Y-m-d'), // Today
        date('Y-m-d', strtotime('-2 days')) // 2 days ago
    ];
    
    $times = ['09:00:00', '10:30:00', '11:00:00', '14:00:00', '15:30:00', '16:00:00', '12:00:00', '13:30:00'];
    
    foreach ($bookings_data as $index => $booking) {
        $stmt->execute([
            $booking[0], // customer_name
            $booking[1], // customer_phone
            $booking[2], // customer_email
            $booking[3], // status
            $booking[4], // notes
            $dates[$index], // booking_date
            $times[$index]  // booking_time
        ]);
        echo "✅ Added booking for: {$booking[0]}\n";
    }
    
    // Get final count
    $count = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    echo "\n🎉 Successfully added sample bookings!\n";
    echo "📊 Total bookings in database: {$count}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
