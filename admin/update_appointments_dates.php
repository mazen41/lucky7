<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Disable foreign key checks temporarily
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// Update appointments to current dates
try {
    $updates = [
        [
            'id' => 1,
            'start_at' => date('Y-m-d H:i:s', strtotime('today 10:00')),
            'end_at' => date('Y-m-d H:i:s', strtotime('today 11:00'))
        ],
        [
            'id' => 2,
            'start_at' => date('Y-m-d H:i:s', strtotime('today 14:00')),
            'end_at' => date('Y-m-d H:i:s', strtotime('today 15:00'))
        ],
        [
            'id' => 3,
            'start_at' => date('Y-m-d H:i:s', strtotime('tomorrow 09:00')),
            'end_at' => date('Y-m-d H:i:s', strtotime('tomorrow 10:00'))
        ],
        [
            'id' => 4,
            'start_at' => date('Y-m-d H:i:s', strtotime('tomorrow 15:00')),
            'end_at' => date('Y-m-d H:i:s', strtotime('tomorrow 16:00'))
        ],
        [
            'id' => 5,
            'start_at' => date('Y-m-d H:i:s', strtotime('+2 days 11:00')),
            'end_at' => date('Y-m-d H:i:s', strtotime('+2 days 12:00'))
        ]
    ];
    
    $update_query = "UPDATE appointments SET start_at = ?, end_at = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    
    foreach ($updates as $update) {
        $update_stmt->execute([
            $update['start_at'],
            $update['end_at'],
            $update['id']
        ]);
        echo "Updated appointment ID {$update['id']} to {$update['start_at']}\n";
    }
    
    echo "All appointments updated to current dates!\n";
    
    // Show updated appointments
    $stmt = $db->query("SELECT * FROM appointments ORDER BY start_at ASC");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nUpdated appointments:\n";
    foreach ($appointments as $apt) {
        echo "ID: {$apt['id']}, Customer: {$apt['customer_name']}, Start: {$apt['start_at']}, Status: {$apt['status']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Re-enable foreign key checks
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
}
?>
