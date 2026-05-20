<?php
/**
 * Add Sample Loyalty Data - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

require_once '../config/database.php';
require_once 'loyalty_engine.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $loyalty = new LoyaltyEngine($db);
    
    echo "🎯 Adding Sample Loyalty Data...\n\n";
    
    // Get existing customers
    $customers_stmt = $db->query("SELECT id, name, phone FROM customers LIMIT 10");
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($customers)) {
        echo "❌ No customers found. Please add customers first.\n";
        exit;
    }
    
    echo "👥 Found " . count($customers) . " customers\n\n";
    
    // 1. Award purchase points for some customers
    echo "💰 Awarding purchase points...\n";
    $purchase_amounts = [250, 180, 320, 450, 120, 380, 290, 150, 500, 220];
    
    foreach ($customers as $index => $customer) {
        if ($index < count($purchase_amounts)) {
            $amount = $purchase_amounts[$index];
            $points = $loyalty->awardPurchasePoints($customer['id'], $amount, 999 + $index);
            echo "  - {$customer['name']}: {$amount} ريال = {$points} نقطة\n";
        }
    }
    
    // 2. Award referral points
    echo "\n🤝 Awarding referral points...\n";
    if (count($customers) >= 4) {
        $referral_points = $loyalty->awardReferralPoints($customers[0]['id'], $customers[1]['id']);
        echo "  - {$customers[0]['name']} أحال {$customers[1]['name']}: {$referral_points} نقطة\n";
        
        $referral_points = $loyalty->awardReferralPoints($customers[2]['id'], $customers[3]['id']);
        echo "  - {$customers[2]['name']} أحال {$customers[3]['name']}: {$referral_points} نقطة\n";
    }
    
    // 3. Award Google review points
    echo "\n⭐ Awarding Google review points...\n";
    foreach (array_slice($customers, 0, 5) as $customer) {
        $rating = rand(4, 5);
        $points = $loyalty->awardGoogleReviewPoints($customer['id'], $rating);
        echo "  - {$customer['name']}: تقييم {$rating} نجوم = {$points} نقطة\n";
    }
    
    // 4. Award birthday points for customers with birthdays
    echo "\n🎂 Awarding birthday points...\n";
    $birthday_customers = array_slice($customers, 0, 3);
    foreach ($birthday_customers as $customer) {
        $points = $loyalty->awardBirthdayPoints($customer['id'], false);
        if ($points > 0) {
            echo "  - {$customer['name']}: نقاط عيد الميلاد = {$points} نقطة\n";
        }
    }
    
    // 5. Add some manual bonus points
    echo "\n🎁 Adding bonus points...\n";
    if (count($customers) >= 2) {
        $stmt = $db->prepare("
            INSERT INTO loyalty_transactions 
            (customer_id, points, transaction_type, earning_type, description_ar, created_at)
            VALUES (?, ?, 'earn', 'bonus', ?, NOW())
        ");
        
        $bonuses = [
            [$customers[0]['id'], 100, 'مكافأة عميل مميز'],
            [$customers[1]['id'], 50, 'مكافأة المراجعة الممتازة']
        ];
        
        foreach ($bonuses as $bonus) {
            $stmt->execute($bonus);
            
            // Update wallet
            $update_stmt = $db->prepare("
                UPDATE loyalty_wallets 
                SET points = points + ?, last_activity_at = NOW()
                WHERE customer_id = ?
            ");
            $update_stmt->execute([$bonus[1], $bonus[0]]);
            
            $loyalty->updateCustomerTier($bonus[0]);
            echo "  - عميل #{$bonus[0]}: {$bonus[2]} = {$bonus[1]} نقطة\n";
        }
    }
    
    // 6. Update all customer tiers
    echo "\n🏆 Updating customer tiers...\n";
    foreach ($customers as $customer) {
        $loyalty->updateCustomerTier($customer['id']);
    }
    
    // 7. Show final statistics
    echo "\n📊 Final Loyalty Statistics:\n";
    $stats = $loyalty->getLoyaltyStats();
    echo "  - Total customers with loyalty: {$stats['total_customers']}\n";
    echo "  - Monthly points distributed: {$stats['monthly_points']}\n";
    
    foreach ($stats['by_tier'] as $tier) {
        echo "  - {$tier['name_ar']}: {$tier['count']} عميل\n";
    }
    
    // 8. Show top customers
    echo "\n🥇 Top 5 Customers by Points:\n";
    $top_stmt = $db->prepare("
        SELECT lw.points, c.name, lt.name_ar as tier_name
        FROM loyalty_wallets lw
        JOIN customers c ON lw.customer_id = c.id
        JOIN loyalty_tiers lt ON lw.tier_id = lt.id
        ORDER BY lw.points DESC
        LIMIT 5
    ");
    $top_stmt->execute();
    $top_customers = $top_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($top_customers as $index => $customer) {
        echo "  " . ($index + 1) . ". {$customer['name']}: {$customer['points']} نقطة ({$customer['tier_name']})\n";
    }
    
    echo "\n🎉 Sample loyalty data added successfully!\n";
    echo "🔗 Visit /admin/loyalty.php to see the loyalty dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
