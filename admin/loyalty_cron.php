<?php
/**
 * Loyalty System Cron Jobs - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 * 
 * This script should be run daily via cron job:
 * 0 2 * * * /usr/bin/php /path/to/loyalty_cron.php
 */

require_once '../config/database.php';
require_once 'loyalty_engine.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $loyalty = new LoyaltyEngine($db);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting loyalty system maintenance...\n";
    
    // 1. Process tier downgrades for inactive customers (3+ months)
    echo "Processing inactivity downgrades...\n";
    $downgrades = $loyalty->processInactivityDowngrades();
    echo "Processed {$downgrades} tier downgrades\n";
    
    // 2. Expire old points (12+ months old)
    echo "Expiring old points...\n";
    $expire_query = "
        SELECT rule_value FROM loyalty_rules 
        WHERE rule_key = 'points_expiry_months' AND is_active = 1
    ";
    $expire_stmt = $db->prepare($expire_query);
    $expire_stmt->execute();
    $expiry_months = $expire_stmt->fetchColumn() ?: 12;
    
    // Find transactions older than expiry period
    $old_transactions_query = "
        SELECT lt.customer_id, SUM(lt.points) as expired_points
        FROM loyalty_transactions lt
        WHERE lt.transaction_type = 'earn'
        AND lt.created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)
        AND lt.id NOT IN (
            SELECT reference_id FROM loyalty_transactions 
            WHERE transaction_type = 'expire' 
            AND reference_type = 'expiry'
        )
        GROUP BY lt.customer_id
        HAVING expired_points > 0
    ";
    
    $old_stmt = $db->prepare($old_transactions_query);
    $old_stmt->execute([$expiry_months]);
    $expired_customers = $old_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_expired = 0;
    foreach ($expired_customers as $customer) {
        $customer_id = $customer['customer_id'];
        $expired_points = $customer['expired_points'];
        
        // Create expiry transaction
        $expire_transaction = $db->prepare("
            INSERT INTO loyalty_transactions 
            (customer_id, points, transaction_type, description_ar, created_at)
            VALUES (?, ?, 'expire', ?, NOW())
        ");
        $expire_transaction->execute([
            $customer_id, 
            $expired_points, 
            "انتهاء صلاحية النقاط - {$expiry_months} شهر"
        ]);
        
        // Update wallet
        $update_wallet = $db->prepare("
            UPDATE loyalty_wallets 
            SET points = GREATEST(0, points - ?)
            WHERE customer_id = ?
        ");
        $update_wallet->execute([$expired_points, $customer_id]);
        
        // Update tier
        $loyalty->updateCustomerTier($customer_id);
        
        $total_expired += $expired_points;
    }
    
    echo "Expired {$total_expired} points for " . count($expired_customers) . " customers\n";
    
    // 3. Auto-confirm birthdays if enabled
    echo "Processing birthday confirmations...\n";
    $auto_confirm_query = "
        SELECT rule_value FROM loyalty_rules 
        WHERE rule_key = 'birthday_auto_confirm' AND is_active = 1
    ";
    $auto_confirm_stmt = $db->prepare($auto_confirm_query);
    $auto_confirm_stmt->execute();
    $auto_confirm = $auto_confirm_stmt->fetchColumn();
    
    $birthday_confirmations = 0;
    if ($auto_confirm == '1') {
        // Find customers with birthdays today
        $today_birthdays_query = "
            SELECT c.id, c.name
            FROM customers c
            JOIN loyalty_wallets lw ON c.id = lw.customer_id
            WHERE c.date_of_birth IS NOT NULL
            AND MONTH(c.date_of_birth) = MONTH(NOW())
            AND DAY(c.date_of_birth) = DAY(NOW())
            AND lw.birthday_confirmed = 0
        ";
        
        $birthday_stmt = $db->prepare($today_birthdays_query);
        $birthday_stmt->execute();
        $birthday_customers = $birthday_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($birthday_customers as $customer) {
            $points = $loyalty->awardBirthdayPoints($customer['id'], true);
            if ($points > 0) {
                $birthday_confirmations++;
                echo "Auto-awarded birthday points to: {$customer['name']}\n";
            }
        }
    }
    
    echo "Auto-confirmed {$birthday_confirmations} birthday rewards\n";
    
    // 4. Generate daily loyalty report
    echo "Generating daily report...\n";
    $report_date = date('Y-m-d');
    
    $daily_stats = [
        'date' => $report_date,
        'points_earned' => 0,
        'points_redeemed' => 0,
        'new_customers' => 0,
        'tier_upgrades' => 0,
        'tier_downgrades' => $downgrades,
        'expired_points' => $total_expired,
        'birthday_rewards' => $birthday_confirmations
    ];
    
    // Get today's points activity
    $today_points_query = "
        SELECT 
            SUM(CASE WHEN transaction_type = 'earn' THEN points ELSE 0 END) as earned,
            SUM(CASE WHEN transaction_type = 'redeem' THEN points ELSE 0 END) as redeemed,
            COUNT(CASE WHEN transaction_type = 'earn' AND earning_type = 'purchase' THEN 1 END) as purchases
        FROM loyalty_transactions 
        WHERE DATE(created_at) = CURDATE()
    ";
    
    $points_stmt = $db->prepare($today_points_query);
    $points_stmt->execute();
    $points_data = $points_stmt->fetch(PDO::FETCH_ASSOC);
    
    $daily_stats['points_earned'] = $points_data['earned'] ?: 0;
    $daily_stats['points_redeemed'] = $points_data['redeemed'] ?: 0;
    
    // Log the report
    $report_log = "
=== LOYALTY SYSTEM DAILY REPORT - {$report_date} ===
Points Earned Today: {$daily_stats['points_earned']}
Points Redeemed Today: {$daily_stats['points_redeemed']}
Tier Downgrades: {$daily_stats['tier_downgrades']}
Expired Points: {$daily_stats['expired_points']}
Birthday Rewards: {$daily_stats['birthday_rewards']}
===============================================
    ";
    
    echo $report_log;
    
    // Save report to file
    $report_file = __DIR__ . '/logs/loyalty_reports/' . $report_date . '.log';
    $report_dir = dirname($report_file);
    if (!is_dir($report_dir)) {
        mkdir($report_dir, 0755, true);
    }
    file_put_contents($report_file, $report_log);
    
    echo "[" . date('Y-m-d H:i:s') . "] Loyalty system maintenance completed successfully\n";
    
} catch (Exception $e) {
    $error_msg = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $error_msg;
    error_log($error_msg);
}
?>
